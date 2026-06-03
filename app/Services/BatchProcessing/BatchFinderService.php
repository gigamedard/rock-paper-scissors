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
    public function findActiveBatchWithLock(int $poolSize, float $baseBet): ?Batch
    {
        Log::channel('batch_polling')->debug("Attempting to find active batch for pool_size: {$poolSize} and base_bet: {$baseBet}");
        
        $query = Batch::where('pool_size', $poolSize)
                    ->where('base_bet', $baseBet)
                    ->whereIn('status', ['running', 'waiting'])
                    ->orderBy('updated_at')
                    ->lockForUpdate();

        // skipLocked() n'est pas supporté par SQLite (utilisé pour les tests locaux)
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
            $query->skipLocked();
        }

        return $query->first();
    }
}