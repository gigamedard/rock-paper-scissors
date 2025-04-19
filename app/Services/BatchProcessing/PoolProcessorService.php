<?php

namespace App\Services\BatchProcessing;

use App\Models\Batch;
use App\Models\Pool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

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

        Log::info("Starting processing loop for batch {$batchContext->id} (Pool Size: {$batchContext->pool_size}). Processing {$poolsToProcess->count()} pools.");

        foreach ($poolsToProcess as $pool) {
            $poolId = $pool->id; // Capture the pool ID here
            // Optional safety check ...
            try {
                Log::debug("Processing Pool ID: {$poolId} (Size: {$pool->pool_size}, Status: {$pool->status})");
                $pool->match(); // Call the core logic
                Log::debug("Finished Processing Pool ID: {$poolId}");
                $processedCount++;
            } catch (Exception $poolError) {
                Log::error("Error processing Pool ID: {$poolId} in Batch ID: {$batchContext->id}. Error: {$poolError->getMessage()}");
                if (!$firstError) {
                    $firstError = $poolError; // Store the first error
                }
                // Decide strategy: Stop batch? Log and continue? Currently continues.
                // if (should_stop_on_error) { break; }
            }
        }

        Log::info("Finished processing loop for batch {$batchContext->id}. Attempted: {$poolsToProcess->count()}. Succeeded/Continued: {$processedCount}. First Error: " . ($firstError ? $firstError->getMessage() : 'None'));

        return ['processedCount' => $processedCount, 'error' => $firstError];
    }
}