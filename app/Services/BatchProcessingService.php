<?php

namespace App\Services;

use App\Services\BatchProcessing\BatchCriteriaService;
use App\Services\BatchProcessing\BatchFinderService;
use App\Services\BatchProcessing\BatchManagerService;
use App\Services\BatchProcessing\PoolFetcherService;
use App\Services\BatchProcessing\PoolProcessorService;
use App\Helpers\UserTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchProcessingService
{
    protected $batchCriteriaService;

    protected $batchFinderService;

    protected $poolFetcherService;

    protected $batchManagerService;

    protected $poolProcessorService;

    public function __construct(
        BatchCriteriaService $batchCriteriaService,
        BatchFinderService $batchFinderService,
        PoolFetcherService $poolFetcherService,
        BatchManagerService $batchManagerService,
        PoolProcessorService $poolProcessorService
    ) {
        $this->batchCriteriaService = $batchCriteriaService;
        $this->batchFinderService = $batchFinderService;
        $this->poolFetcherService = $poolFetcherService;
        $this->batchManagerService = $batchManagerService;
        $this->poolProcessorService = $poolProcessorService;
    }

    public function processBatch(float $baseBet): array
    {
        $criteriaResult = $this->batchCriteriaService->getTargetPoolSize();
        if ($criteriaResult['error']) {
            return ['status' => 'error', 'message' => $criteriaResult['error'], 'http_code' => 500];
        }
        $targetPoolSize = $criteriaResult['targetPoolSize'];

        $poolsToProcess = collect();
        $batchForProcessing = null;

        try {
            $resultData = DB::transaction(function () use (
                $targetPoolSize,
                $baseBet,
                &$poolsToProcess,
                &$batchForProcessing
            ) {
                $batch = $this->batchFinderService->findActiveBatchWithLock($targetPoolSize, $baseBet);

                if (! $batch) {
                    if (! $this->poolFetcherService->processablePoolsExist($targetPoolSize, $baseBet)) {
                        return ['status' => 'no_work', 'message' => "No active batch or available pools for pool_size {$targetPoolSize} base_bet {$baseBet}."];
                    }
                    $initialPools = $this->poolFetcherService->fetchInitialPools($targetPoolSize, $baseBet, config('pool.batch_initial_limit', 50));
                    if ($initialPools->isEmpty()) {
                        return ['status' => 'no_work', 'message' => "No pools available to form an initial batch for pool_size {$targetPoolSize} base_bet {$baseBet}."];
                    }
                    $createdBatch = $this->batchManagerService->createBatch($targetPoolSize, $baseBet, $initialPools);

                    return ['status' => 'created', 'message' => "New batch {$createdBatch->id} for pool_size {$targetPoolSize} base_bet {$baseBet} created and is {$createdBatch->status}."];
                }

                $isStale = $batch->updated_at->diffInSeconds(now()) > config('pool.batch_ttl_seconds', 60);

                if ($batch->status === 'waiting' && $batch->number_of_pools < $batch->max_size) {
                    $needed = $batch->max_size - $batch->number_of_pools;
                    $newPools = $this->poolFetcherService->fetchPoolsToLoad($batch, $needed);
                    if ($newPools->isEmpty()) {
                        if ($isStale && $batch->number_of_pools > 0) {
                            Log::info("Batch {$batch->id} is stale (waiting > TTL). Forcing transition to running with {$batch->number_of_pools} pools.");
                            // Do not return here. Let it fall through to the 'running' block below.
                        } else {
                            $msg = ($batch->number_of_pools > 0)
                                ? "Batch {$batch->id} remains waiting with {$batch->number_of_pools} pools, no new pools found."
                                : "Batch {$batch->id} remains waiting and empty, no new pools found.";

                            return ['status' => 'no_change', 'message' => $msg];
                        }
                    } else {
                        $this->batchManagerService->loadWaitingBatch($batch, $newPools);
                        // Refresh staleness check since batch was just updated
                        $isStale = false;
                        
                        // If the batch is STILL not full after loading, return 'updated'.
                        if ($batch->number_of_pools < $batch->max_size) {
                            return ['status' => 'updated', 'message' => "Batch {$batch->id} (pool_size {$batch->pool_size}) updated with {$newPools->count()} pools. Status: {$batch->status}"];
                        }
                    }
                }

                if ($batch->status === 'waiting' && ($batch->number_of_pools >= $batch->max_size || ($isStale && $batch->number_of_pools > 0))) {
                    if ($batch->status !== 'running') {
                        $batch->status = 'running';
                        $batch->save();
                    }
                    $retrievedPools = $this->poolFetcherService->fetchPoolsForProcessing($batch);
                    if ($retrievedPools->isEmpty()) {
                        $batch->status = 'settled';
                        $batch->save();

                        return ['status' => 'no_work', 'message' => "Batch {$batch->id} found no processable pools in its range. Transitioned to settled to avoid blocking tier."];
                    }
                    $poolsToProcess = $retrievedPools;
                    $batchForProcessing = $batch;

                    return ['status' => 'processing_deferred'];
                }

                return ['status' => 'no_action', 'message' => "Batch {$batch->id} status '{$batch->status}' did not trigger action."];
            });

            if (isset($resultData['status']) && $resultData['status'] === 'processing_deferred') {
                if (! $batchForProcessing || $poolsToProcess->isEmpty()) {
                    return ['status' => 'error', 'message' => 'Internal error during deferred processing setup.', 'http_code' => 500];
                }
                $processingResult = $this->poolProcessorService->processPools($poolsToProcess, $batchForProcessing);
                $updatedBatch = $this->batchManagerService->updateBatchStatusAfterProcessing($batchForProcessing->id, ! is_null($processingResult['error']), $processingResult['processedCount']);

                if ($processingResult['error']) {
                    return [
                        'status' => 'partial_error',
                        'message' => "Batch {$updatedBatch->id} (pool_size {$updatedBatch->pool_size}) processed with errors. Final Status: {$updatedBatch->status}",
                        'processed_count' => $processingResult['processedCount'],
                        'total_in_batch' => $poolsToProcess->count(),
                        'iteration' => $updatedBatch->iteration_count,
                        'error' => $processingResult['error']->getMessage(),
                        'http_code' => 207,
                    ];
                } else {
                    return [
                        'status' => 'success',
                        'message' => "Batch {$updatedBatch->id} (pool_size {$updatedBatch->pool_size}) processed successfully. Final Status: {$updatedBatch->status}",
                        'processed_count' => $processingResult['processedCount'],
                        'iteration' => $updatedBatch->iteration_count,
                        'http_code' => 200,
                    ];
                }
            } elseif (isset($resultData['status'])) {
                $httpStatusCode = match ($resultData['status']) {
                    'no_work', 'no_change' => 200,
                    'created', 'updated' => 201,
                    'error', 'no_action' => 400,
                    default => 200,
                };

                return ['status' => $resultData['status'], 'message' => $resultData['message'], 'target_pool_size' => $targetPoolSize, 'http_code' => $httpStatusCode];
            } else {
                return ['status' => 'error', 'message' => 'An unexpected server error occurred (unknown state).', 'http_code' => 500];
            }
        } catch (\Exception $e) {
            Log::error('Error in BatchProcessingService: '.$e->getMessage());

            return ['status' => 'error', 'message' => 'An error occurred during batch processing: '.$e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * Round-robin: process ONE bet tier per call, cycling through active tiers.
     * Discovers active base_bet values from the pools table and rotates.
     */
    public function processAllBetTiers(): array
    {
        $criteriaResult = $this->batchCriteriaService->getTargetPoolSize();
        if ($criteriaResult['error']) {
            return ['status' => 'error', 'message' => $criteriaResult['error'], 'http_code' => 500];
        }
        $targetPoolSize = $criteriaResult['targetPoolSize'];

        $activeBetTiers = \App\Models\Pool::whereIn('status', ['from_server_waitting', 'batched'])
            ->distinct()
            ->pluck('base_bet')
            ->toArray();

        if (empty($activeBetTiers)) {
            return ['status' => 'no_work', 'message' => 'No waiting pools found for any base_bet tier.', 'http_code' => 200];
        }

        sort($activeBetTiers, SORT_NUMERIC);

        $cacheKey = 'bet_tier_round_robin_index';
        $currentIndex = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0) % count($activeBetTiers);

        $tierBet = $activeBetTiers[$currentIndex];

        $nextIndex = ($currentIndex + 1) % count($activeBetTiers);
        \Illuminate\Support\Facades\Cache::put($cacheKey, $nextIndex, now()->addMinutes(5));

        $result = $this->processBatch((float) $tierBet);

        UserTracker::info("BatchProcessingService [Round-Robin {$currentIndex}/".(count($activeBetTiers) - 1)."]: Processing tier {$tierBet} → {$result['status']}", ['tier' => $tierBet, 'status' => $result['status']]);

        return [
            'status' => $result['status'],
            'message' => $result['message'],
            'current_tier' => $tierBet,
            'tier_index' => $currentIndex,
            'next_tier_index' => $nextIndex,
            'active_tiers' => $activeBetTiers,
            'processed_count' => $result['processed_count'] ?? 0,
            'http_code' => $result['http_code'] ?? 200,
        ];
    }
}
