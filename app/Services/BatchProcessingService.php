<?php

namespace App\Services;

use App\Models\ArrayIndex;
use App\Services\BatchProcessing\BatchCriteriaService;
use App\Services\BatchProcessing\BatchFinderService;
use App\Services\BatchProcessing\BatchManagerService;
use App\Services\BatchProcessing\PoolFetcherService;
use App\Services\BatchProcessing\PoolProcessorService;
use Illuminate\Http\JsonResponse;
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

                if (!$batch) {
                    if (!$this->poolFetcherService->processablePoolsExist($targetPoolSize, $baseBet)) {
                        return ['status' => 'no_work', 'message' => "No active batch or available pools for pool_size {$targetPoolSize} base_bet {$baseBet}."];
                    }
                    $initialPools = $this->poolFetcherService->fetchInitialPools($targetPoolSize, $baseBet, config('pool.batch_initial_limit', 50));
                    if ($initialPools->isEmpty()) {
                        return ['status' => 'no_work', 'message' => "No pools available to form an initial batch for pool_size {$targetPoolSize} base_bet {$baseBet}."];
                    }
                    $createdBatch = $this->batchManagerService->createBatch($targetPoolSize, $baseBet, $initialPools);
                    return ['status' => 'created', 'message' => "New batch {$createdBatch->id} for pool_size {$targetPoolSize} base_bet {$baseBet} created and is {$createdBatch->status}."];
                }

                if ($batch->status === 'waiting' && $batch->number_of_pools < $batch->max_size) {
                    $needed = $batch->max_size - $batch->number_of_pools;
                    $newPools = $this->poolFetcherService->fetchPoolsToLoad($batch, $needed);
                    if ($newPools->isEmpty()) {
                        $msg = ($batch->number_of_pools > 0)
                            ? "Batch {$batch->id} remains waiting with {$batch->number_of_pools} pools, no new pools found."
                            : "Batch {$batch->id} remains waiting and empty, no new pools found.";
                        return ['status' => 'no_change', 'message' => $msg];
                    }
                    $this->batchManagerService->loadWaitingBatch($batch, $newPools);
                    return ['status' => 'updated', 'message' => "Batch {$batch->id} (pool_size {$batch->pool_size}) updated with {$newPools->count()} pools. Status: {$batch->status}"];
                }

                if ($batch->status === 'waiting' && $batch->number_of_pools >= $batch->max_size) {
                    if ($batch->status !== 'running') {
                        $batch->status = 'running';
                        $batch->save();
                    }
                    $retrievedPools = $this->poolFetcherService->fetchPoolsForProcessing($batch);
                    if ($retrievedPools->isEmpty()) {
                        $batch->status = 'waiting';
                        $batch->save();
                        return ['status' => 'error', 'message' => "Batch {$batch->id} found no pools in its defined range. Status reverted to waiting."];
                    }
                    $poolsToProcess = $retrievedPools;
                    $batchForProcessing = $batch;
                    return ['status' => 'processing_deferred'];
                }

                return ['status' => 'no_action', 'message' => "Batch {$batch->id} status '{$batch->status}' did not trigger action."];
            });

            if (isset($resultData['status']) && $resultData['status'] === 'processing_deferred') {
                if (!$batchForProcessing || $poolsToProcess->isEmpty()) {
                    return ['status' => 'error', 'message' => 'Internal error during deferred processing setup.', 'http_code' => 500];
                }
                $processingResult = $this->poolProcessorService->processPools($poolsToProcess, $batchForProcessing);
                $updatedBatch = $this->batchManagerService->updateBatchStatusAfterProcessing($batchForProcessing->id, !is_null($processingResult['error']), $processingResult['processedCount']);

                if ($processingResult['error']) {
                    return [
                        'status' => 'partial_error',
                        'message' => "Batch {$updatedBatch->id} (pool_size {$updatedBatch->pool_size}) processed with errors. Final Status: {$updatedBatch->status}",
                        'processed_count' => $processingResult['processedCount'],
                        'total_in_batch' => $poolsToProcess->count(),
                        'iteration' => $updatedBatch->iteration_count,
                        'error' => $processingResult['error']->getMessage(),
                        'http_code' => 207
                    ];
                } else {
                    return [
                        'status' => 'success',
                        'message' => "Batch {$updatedBatch->id} (pool_size {$updatedBatch->pool_size}) processed successfully. Final Status: {$updatedBatch->status}",
                        'processed_count' => $processingResult['processedCount'],
                        'iteration' => $updatedBatch->iteration_count,
                        'http_code' => 200
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
            Log::error('Error in BatchProcessingService: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'An error occurred during batch processing: ' . $e->getMessage(), 'http_code' => 500];
        }
    }
}
