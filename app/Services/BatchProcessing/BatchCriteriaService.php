<?php

namespace App\Services\BatchProcessing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BatchCriteriaService
{
    const POOL_SIZE_INDEX_CACHE_KEY = 'batch_pool_size_index';

    /**
     * Determines the target pool size for the current batch processing run.
     *
     * @return array{targetPoolSize: int|null, error: string|null}
     */
    public function getTargetPoolSize(): array
    {
        $targetPoolSizes = Config::get('pool.size', []);

        if (empty($targetPoolSizes)) {
            $errorMsg = "Configuration key 'pool.size' is empty or not defined.";
            Log::error($errorMsg);
            return ['targetPoolSize' => null, 'error' => $errorMsg];
        }

        $currentIndex = Cache::get(self::POOL_SIZE_INDEX_CACHE_KEY, 0);
        $currentIndex = is_numeric($currentIndex) ? (int)$currentIndex % count($targetPoolSizes) : 0;

        $targetPoolSize = $targetPoolSizes[$currentIndex];

        $nextIndex = ($currentIndex + 1) % count($targetPoolSizes);
        Cache::put(self::POOL_SIZE_INDEX_CACHE_KEY, $nextIndex);

        Log::info("Selected Target Pool Size for this run: {$targetPoolSize} (Index: {$currentIndex} from config('pool.size'), Next Index: {$nextIndex})");

        return ['targetPoolSize' => $targetPoolSize, 'error' => null];
    }
}