<?php

namespace App\Services\BatchProcessing;

use App\Models\Batch; // <-- Add this use statement
use App\Models\Pool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PoolFetcherService
{
    const POOL_STATUS_WAITING = 'from_server_waitting';

    /**
     * Checks if processable pools exist for a given pool size.
     *
     * @param int $poolSize
     * @return bool
     */
    public function processablePoolsExist(int $poolSize): bool
    {
        // Find the last pool ID that was included in any previous batch for this size.
        $lastBatchedPoolId = Batch::where('pool_size', $poolSize)->max('last_pool_id') ?? 0;

        $exists = Pool::where('pool_size', $poolSize)
            ->where('status', self::POOL_STATUS_WAITING)
            ->where('id', '>', $lastBatchedPoolId) // <-- Check for pools after the last batch
            ->exists();

        Log::debug("Check processable pools exist for pool size ($poolSize) after ID ($lastBatchedPoolId): " . ($exists ? 'Yes' : 'No'));

        return $exists;
    }

    /**
     * Fetches initial pools to create a new batch, starting after the last known batch.
     *
     * @param int $poolSize
     * @param int $limit
     * @return Collection<Pool>
     */
    public function fetchInitialPools(int $poolSize, int $limit): Collection
    {
        // --- MODIFICATION START ---

        // 1. Find the maximum 'last_pool_id' from existing batches for this pool size.
        // This ensures the new batch starts after the previous one.
        $lastBatchedPoolId = Batch::where('pool_size', $poolSize)->max('last_pool_id') ?? 0;

        Log::debug("Fetching initial ($limit) pools for pool_size ($poolSize), starting after Pool ID ($lastBatchedPoolId)");

        // 2. Modify the query to fetch pools with an ID greater than the last one.
        return Pool::where('pool_size', $poolSize)
            ->where('status', self::POOL_STATUS_WAITING)
            ->where('id', '>', $lastBatchedPoolId) // <-- This is the key change
            ->orderBy('id')
            ->take($limit)
            ->get();

        // --- MODIFICATION END ---
    }

    /**
     * Fetches new pools to add to a waiting batch.
     *
     * @param Batch $batch
     * @param int $needed
     * @return Collection<Pool>
     */
    public function fetchPoolsToLoad(Batch $batch, int $needed): Collection
    {
        Log::debug("Fetching ($needed) pools to load into batch ($batch->id) (pool_size ($batch->pool_size)), after pool ID {$batch->last_pool_id}");

        return Pool::where('pool_size', $batch->pool_size)
            ->where('status', self::POOL_STATUS_WAITING)
            ->where('id', '>', $batch->last_pool_id) // Ensure pools after the current last one
            ->orderBy('id')
            ->take($needed)
            ->get();
    }

    /**
     * Fetches all pools within the ID range of a given batch for processing.
     *
     * @param Batch $batch
     * @return Collection<Pool>
     */
    public function fetchPoolsForProcessing(Batch $batch): Collection
    {
        Log::debug("Fetching pools for processing batch ($batch->id) (pool_size ($batch->pool_size)), range: ($batch->first_pool_id)-($batch->last_pool_id)");

        // Assumes first_pool_id and last_pool_id accurately define the batch scope
        return Pool::whereBetween('id', [$batch->first_pool_id, $batch->last_pool_id])
            // Optional safety filter: ->where('pool_size', $batch->pool_size)
            ->orderBy('id')
            ->get();
    }
}