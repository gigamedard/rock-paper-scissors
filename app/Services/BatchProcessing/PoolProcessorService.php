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

        foreach ($poolsToProcess as $pool) {
            $poolId = $pool->id; // Capture the pool ID here
            // Optional safety check ...
            try {
                Log::debug("Processing Pool ID: {$poolId} (Size: {$pool->pool_size}, Status: {$pool->status})");
                Web3Helper::marker(20, "model pool", "processPools", "before match() for pool ID: {$poolId}");
                $pool->match(); // Call the core logic
                Web3Helper::marker(20, "model pool", "processPools", "after match() for pool ID: {$poolId}");
                Log::debug("Finished Processing Pool ID: {$poolId}");
                $processedCount++;
            } catch (Exception $poolError) {
                Log::error("Error processing Pool ID: {$poolId} in Batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                Web3Helper::marker(20, "model pool", "processPools", "Error processing pool ID: {$poolId} in batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                // Capture the first error encountered
                if (!$firstError) {
                    $firstError = $poolError; // Store the first error
                }
                // Decide strategy: Stop batch? Log and continue? Currently continues.
                // if (should_stop_on_error) { break; }
            }
        }

        Log::info("Finished processing loop for batch {$batchContext->id}. Attempted: {$poolsToProcess->count()}. Succeeded/Continued: {$processedCount}. First Error: " . ($firstError ? $firstError->getMessage() : 'None'));
        Web3Helper::marker(20, "service pool", "processPools", "Finished processing loop for batch ID: {$batchContext->id}. Processed Count: {$processedCount}, First Error: " . ($firstError ? $firstError->getMessage() : 'None'));
        return ['processedCount' => $processedCount, 'error' => $firstError];
    }
}