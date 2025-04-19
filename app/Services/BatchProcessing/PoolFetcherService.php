<?php

namespace App\Services\BatchProcessing;

use App\Models\Batch;
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
        $exists = Pool::where('pool_size', $poolSize)
                      ->where('status', self::POOL_STATUS_WAITING)
                      ->exists();
        Log::debug("Check processable pools exist for pool_size {$poolSize}: " . ($exists ? 'Yes' : 'No'));
        return $exists;
    }

    /**
     * Fetches initial pools to create a new batch.
     *
     * @param int $poolSize
     * @param int $limit
     * @return Collection<Pool>
     */
    public function fetchInitialPools(int $poolSize, int $limit): Collection
    {
        Log::debug("Fetching initial {$limit} pools for pool_size {$poolSize}");
        return Pool::where('pool_size', $poolSize)
                   ->where('status', self::POOL_STATUS_WAITING)
                   ->orderBy('id')
                   ->take($limit)
                   ->get();
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
        Log::debug("Fetching {$needed} pools to load into batch {$batch->id} (pool_size {$batch->pool_size}), after pool ID {$batch->last_pool_id}");
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
        Log::debug("Fetching pools for processing batch {$batch->id} (pool_size {$batch->pool_size}), range: {$batch->first_pool_id}-{$batch->last_pool_id}");
        // Assumes first_pool_id and last_pool_id accurately define the batch scope
        return Pool::whereBetween('id', [$batch->first_pool_id, $batch->last_pool_id])
                   // Optional safety filter: ->where('pool_size', $batch->pool_size)
                   ->orderBy('id')
                   ->get();
    }
}