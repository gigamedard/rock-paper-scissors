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

        Log::info("Creating new batch for pool_size {$poolSize}. First Pool ID: {$firstPoolId}, Last Pool ID: {$lastPoolId}, Count: {$poolCount}");

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

        Log::info("Created new batch ID: {$batch->id}. Status: {$batch->status}. Pool Size: {$batch->pool_size}");
        // NOTE: Pool status cannot be updated here due to enum constraints.
        return $batch;
    }

    /**
     * Loads new pools into a waiting batch.
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

        $newPoolCount = $newPools->count();
        $newLastPoolId = $newPools->last()->id;

        $batch->number_of_pools += $newPoolCount;
        $batch->last_pool_id = $newLastPoolId;
        // Status remains 'waiting'
        $batch->save(); // Save changes to the locked batch

        Log::info("Added {$newPoolCount} pools to batch {$batch->id}. New Pool Count: {$batch->number_of_pools}. New Last ID: {$batch->last_pool_id}. Status remains: {$batch->status}");
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
             if ($processingErrorOccurred /* more specific condition? */ || $currentIteration >= $maxIterations) {
                 $newStatus = 'settled';
                 if($processingErrorOccurred) Log::warning("Settling batch {$batchToUpdate->id} due to processing error.");
                 else Log::info("Settling batch {$batchToUpdate->id} after reaching max iterations ({$currentIteration}/{$maxIterations}).");
                 // Optional: $batchToUpdate->iteration_count = 0;
             } else {
                 $newStatus = 'waiting';
                 Log::info("Batch {$batchToUpdate->id} processed iteration {$currentIteration}. Status set back to 'waiting'.");
             }

             $batchToUpdate->status = $newStatus;
             $batchToUpdate->save();

             Log::info("Batch {$batchToUpdate->id} final status after processing: {$batchToUpdate->status}");

             return $batchToUpdate; // Return the updated batch
         });
     }
}