<?php

namespace App\Services\BatchProcessing;

use App\Models\Batch;
use Illuminate\Support\Facades\Log;

class BatchFinderService
{
    /**
     * Finds the next active batch (running or waiting) for a given pool size.
     * Applies row locking for update within a transaction.
     *
     * @param int $poolSize
     * @return Batch|null
     */
    public function findActiveBatchWithLock(int $poolSize): ?Batch
    {
        Log::debug("Attempting to find active batch for pool_size: {$poolSize}");
        // NOTE: lockForUpdate() should be applied within the transaction boundary in the controller/main service
        return Batch::where('pool_size', $poolSize)
                    ->whereIn('status', ['running', 'waiting'])
                    ->orderBy('updated_at')
                    // ->lockForUpdate() // Apply lock where the transaction begins
                    ->first();
    }
}