<?php

namespace App\Services\BatchProcessing;

use App\Models\Batch;
use App\Models\Pool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Helpers\Web3Helper; // Assuming you have a Web3Helper class for sorting

class PoolProcessorService
{
    /**
     * Processes a collection of pools belonging to a specific batch.
     *
     * @param Collection<Pool> $poolsToProcess
     * @param Batch $batchContext Context for logging/validation.
     * @return array{processedCount: int, error: Exception|null}
     */
    public function processPools(Collection $poolsToProcess, Batch $batchContext): array
    {
        $processedCount = 0;
        $firstError = null;
        Web3Helper::marker(20, "service pool", "processPools", "Starting processing loop for batch ID: {$batchContext->id}");
        
        Log::info("Starting processing loop for batch {$batchContext->id} (Pool Size: {$batchContext->pool_size}). Processing {$poolsToProcess->count()} pools.");

        // Vérification de la disponibilité de Swoole (Octane/Production vs CLI/Tests)
        if (class_exists('OpenSwoole\Coroutine') || class_exists('Swoole\Coroutine')) {
            $isSwoole = class_exists('Swoole\Coroutine');
            $isOpenSwoole = class_exists('OpenSwoole\Coroutine');

            if ($isOpenSwoole && class_exists('OpenSwoole\Coroutine\Barrier')) {
                $barrier = \OpenSwoole\Coroutine\Barrier::create();
                $atomicCount = new \OpenSwoole\Atomic(0);
                $errorChannel = new \OpenSwoole\Coroutine\Channel($poolsToProcess->count() > 0 ? $poolsToProcess->count() : 1);
            } elseif ($isSwoole && class_exists('Swoole\Coroutine\Barrier')) {
                $barrier = \Swoole\Coroutine\Barrier::create();
                $atomicCount = new \Swoole\Atomic(0);
                $errorChannel = new \Swoole\Coroutine\Channel($poolsToProcess->count() > 0 ? $poolsToProcess->count() : 1);
            } else {
                goto fallback;
            }

            foreach ($poolsToProcess as $pool) {
                $poolId = $pool->id;
                // Lancement de chaque match dans une coroutine concurrente
                $goFunc = function () use ($barrier, $pool, $poolId, $batchContext, $atomicCount, $errorChannel, $isOpenSwoole) {
                    $cid = $isOpenSwoole ? \OpenSwoole\Coroutine::getCid() : \Swoole\Coroutine::getCid();
                    try {
                        Log::debug("[Coroutine #{$cid}] Processing Pool ID: {$poolId} (Size: {$pool->pool_size}, Status: {$pool->status})");
                        Web3Helper::marker(20, "model pool", "processPools", "before match() for pool ID: {$poolId} in Coroutine #{$cid}");
                        $pool->match(); // Call the core logic
                        Web3Helper::marker(20, "model pool", "processPools", "after match() for pool ID: {$poolId} in Coroutine #{$cid}");
                        Log::debug("[Coroutine #{$cid}] Finished Processing Pool ID: {$poolId}");
                        $atomicCount->add(1);
                    } catch (Exception $poolError) {
                        Log::error("[Coroutine #{$cid}] Error processing Pool ID: {$poolId} in Batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                        Web3Helper::marker(20, "model pool", "processPools", "Error processing pool ID: {$poolId} in batch ID: {$batchContext->id} in Coroutine #{$cid}. Error: {$poolError->getMessage()}");
                        $errorChannel->push($poolError);
                    }
                };
                
                if ($isOpenSwoole && function_exists('OpenSwoole\Coroutine\go')) {
                    \OpenSwoole\Coroutine\go($goFunc);
                } elseif ($isSwoole && function_exists('go')) {
                    go($goFunc);
                } elseif (function_exists('go')) {
                    go($goFunc);
                } else {
                    $goFunc(); // Fallback
                }
            }

            // Attente de la fin de toutes les coroutines
            if ($isOpenSwoole && class_exists('OpenSwoole\Coroutine\Barrier')) {
                \OpenSwoole\Coroutine\Barrier::wait($barrier);
            } else {
                \Swoole\Coroutine\Barrier::wait($barrier);
            }

            $processedCount = $atomicCount->get();
            $firstError = !$errorChannel->isEmpty() ? $errorChannel->pop() : null;
        } else {
            fallback:
            // Mode séquentiel de repli pour la suite de tests PHPUnit et le développement local sans Swoole
            foreach ($poolsToProcess as $pool) {
                $poolId = $pool->id;
                try {
                    Log::debug("[Sync] Processing Pool ID: {$poolId} (Size: {$pool->pool_size}, Status: {$pool->status})");
                    Web3Helper::marker(20, "model pool", "processPools", "before match() for pool ID: {$poolId}");
                    $pool->match();
                    Web3Helper::marker(20, "model pool", "processPools", "after match() for pool ID: {$poolId}");
                    Log::debug("[Sync] Finished Processing Pool ID: {$poolId}");
                    $processedCount++;
                } catch (Exception $poolError) {
                    Log::error("[Sync] Error processing Pool ID: {$poolId} in Batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                    Web3Helper::marker(20, "model pool", "processPools", "Error processing pool ID: {$poolId} in batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                    if (!$firstError) {
                        $firstError = $poolError;
                    }
                }
            }
        }

        Log::info("Finished processing loop for batch {$batchContext->id}. Attempted: {$poolsToProcess->count()}. Succeeded/Continued: {$processedCount}. First Error: " . ($firstError ? $firstError->getMessage() : 'None'));
        Web3Helper::marker(20, "service pool", "processPools", "Finished processing loop for batch ID: {$batchContext->id}. Processed Count: {$processedCount}, First Error: " . ($firstError ? $firstError->getMessage() : 'None'));
        return ['processedCount' => $processedCount, 'error' => $firstError];
    }
}