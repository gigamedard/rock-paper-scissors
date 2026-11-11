<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pré-synchro on-chain du solde d'un humain en fin de session.
 *
 * Le bridge fait `updateUserBalance(wallet, balance)` avant de signer le claim.
 * En lançant cette synchro dès la fin de session (et non au clic), on évite le
 * revert « Amount exceeds user balance » quand la tx de synchro est encore en
 * file. La signature de claim est aussi pré-générée.
 */
class SyncUserBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        try {
            app(SessionManager::class)->preSyncHumanClaim($user);
        } catch (\Throwable $e) {
            Log::warning('[SYNC_BALANCE] user ' . $this->userId . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
