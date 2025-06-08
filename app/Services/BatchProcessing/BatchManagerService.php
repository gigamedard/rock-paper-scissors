<?php

namespace App\Services\BatchProcessing;

use App\Models\Batch;
use App\Models\Pool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Exception;

class BatchManagerService
{
    /**
     * Creates a new batch record.
     *
     * @param int $poolSize
     * @param Collection<Pool> $initialPools
     * @return Batch
     */
    public function createBatch(int $poolSize, Collection $initialPools): Batch
    {
        $firstPoolId = $initialPools->first()->id;
        $lastPoolId = $initialPools->last()->id;
        $poolCount = $initialPools->count();

        $batchMaxSizeDefault = Config::get('pool.batch_max_size', 100);
        $batchMaxIterationsDefault = Config::get('pool.batch_max_iterations', 5);

        Log::info("Creating new batch for pool size ($poolSize). First Pool ID: ($firstPoolId), Last Pool ID: ($lastPoolId), Count: ($poolCount)");
        
        $batch = Batch::create([
            'pool_size' => $poolSize,
            'first_pool_id' => $firstPoolId,
            'last_pool_id' => $lastPoolId,
            'number_of_pools' => $poolCount,
            'max_size' => $batchMaxSizeDefault,
            'status' => 'waiting',
            'iteration_count' => 0,
            'max_iterations' => $batchMaxIterationsDefault,
        ]);

        // --- MODIFICATION START ---
        // Atomically update the status of the pools assigned to this new batch.
        $poolIds = $initialPools->pluck('id');
        Pool::whereIn('id', $poolIds)->update(['status' => 'batched']);
        Log::info("Updated status to 'batched' for {$poolIds->count()} pools for new batch ID: ($batch->id).");
        // --- MODIFICATION END ---

        Log::info("Created new batch ID: ($batch->id). Status: {$batch->status}. Pool Size: ($batch->pool_size)");
        
        return $batch;
    }

    /**
     * Loads new pools into a waiting batch and updates their status.
     * Assumes this is called within a transaction holding a lock on the batch.
     *
     * @param Batch $batch The locked batch instance.
     * @param Collection<Pool> $newPools
     * @return bool True if updated, false otherwise.
     */
    public function loadWaitingBatch(Batch $batch, Collection $newPools): bool
    {
        if ($newPools->isEmpty()) {
            return false;
        }

        $batch->number_of_pools += $newPools->count();
        $batch->last_pool_id = $newPools->last()->id;
        $batch->save();

        // --- MODIFICATION START ---
        // Atomically update the status of the new pools added to the batch.
        $newPoolIds = $newPools->pluck('id');
        Pool::whereIn('id', $newPoolIds)->update(['status' => 'batched']);
        Log::info("Updated status to 'batched' for {$newPoolIds->count()} new pools added to batch ($batch->id).");
        // --- MODIFICATION END ---
        
        Log::info("Added {$newPools->count()} pools to batch ($batch->id). New Pool Count: ($batch->number_of_pools). New Last ID: ($batch->last_pool_id). Status remains: ($batch->status)");
        return true;
    }


     /**
      * Updates batch status and iteration count after a processing attempt.
      * Handles its own transaction and locking.
      *
      * @param int $batchId
      * @param bool $processingErrorOccurred Did a significant error happen during processing?
      * @param int $processedPoolCount How many pools were successfully processed (or attempted before error).
      * @return Batch The updated batch.
      * @throws Exception If batch update fails.
      */
     public function updateBatchStatusAfterProcessing(int $batchId, bool $processingErrorOccurred, int $processedPoolCount): Batch
     {
         return DB::transaction(function() use ($batchId, $processingErrorOccurred, $processedPoolCount) {
             $batchToUpdate = Batch::lockForUpdate()->findOrFail($batchId);

             $currentIteration = $batchToUpdate->iteration_count;
             $maxIterations = $batchToUpdate->max_iterations;

             // Decide whether to increment iteration count
             $shouldIncrement = !$processingErrorOccurred || ($processingErrorOccurred && $processedPoolCount > 0); // Adjust logic as needed

             if ($shouldIncrement) {
                 $batchToUpdate->increment('iteration_count');
                 $currentIteration = $batchToUpdate->iteration_count; // Get updated value
                 Log::info("Incremented iteration count for batch {$batchToUpdate->id} to {$currentIteration}.");
             } else {
                 Log::warning("Processing error likely occurred or no pools succeeded for batch {$batchToUpdate->id}, iteration count not incremented.");
             }

             // Decide final status
            if ($currentIteration >= $maxIterations) {
                $newStatus = 'settled';
            } elseif ($processingErrorOccurred) {
                $newStatus = 'waiting'; // Retry next round
            } else {
                $newStatus = 'waiting';
            }
            Log::info("Batch {$batchToUpdate->id} processing completed. Processed Pools: {$processedPoolCount}. Current Iteration: {$currentIteration}. Max Iterations: {$maxIterations}. Processing Error: " . ($processingErrorOccurred ? 'Yes' : 'No') . ". Setting status to: {$newStatus}");

             $batchToUpdate->status = $newStatus;
             $batchToUpdate->save();

             Log::info("Batch {$batchToUpdate->id} final status after processing: {$batchToUpdate->status}");

             return $batchToUpdate; // Return the updated batch
         });
     }
}