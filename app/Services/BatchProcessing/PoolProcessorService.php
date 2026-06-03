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
        if (class_exists('Swoole\Coroutine')) {
            $barrier = \Swoole\Coroutine\Barrier::create();
            foreach ($poolsToProcess as $pool) {
                $poolId = $pool->id;
                // Lancement de chaque match dans une coroutine concurrente
                go(function () use ($barrier, $pool, $poolId, $batchContext, &$processedCount, &$firstError) {
                    $cid = \Swoole\Coroutine::getCid();
                    try {
                        Log::debug("[Coroutine #{$cid}] Processing Pool ID: {$poolId} (Size: {$pool->pool_size}, Status: {$pool->status})");
                        Web3Helper::marker(20, "model pool", "processPools", "before match() for pool ID: {$poolId} in Coroutine #{$cid}");
                        $pool->match(); // Call the core logic
                        Web3Helper::marker(20, "model pool", "processPools", "after match() for pool ID: {$poolId} in Coroutine #{$cid}");
                        Log::debug("[Coroutine #{$cid}] Finished Processing Pool ID: {$poolId}");
                        $processedCount++;
                    } catch (Exception $poolError) {
                        Log::error("[Coroutine #{$cid}] Error processing Pool ID: {$poolId} in Batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                        Web3Helper::marker(20, "model pool", "processPools", "Error processing pool ID: {$poolId} in batch ID: {$batchContext->id} in Coroutine #{$cid}. Error: {$poolError->getMessage()}");
                        // Capture de la première erreur de manière sécurisée en coroutine
                        if (!$firstError) {
                            $firstError = $poolError;
                        }
                    }
                });
            }
            // Barrière de synchronisation : attend que toutes les coroutines aient terminé
            \Swoole\Coroutine\Barrier::wait($barrier);
        } else {
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