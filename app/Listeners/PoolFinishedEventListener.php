<?php

namespace App\Listeners;

use App\Events\PoolFinishedEvent;
use App\Events\SessionFinishedEvent;
use App\Services\HistoricalFightService;
use App\Models\Pool;
use Illuminate\Support\Facades\Log;

class PoolFinishedEventListener
{
    protected $historicalFightService;

    public function __construct(HistoricalFightService $historicalFightService)
    {
        $this->historicalFightService = $historicalFightService;
    }

    /**
     * Handle the event.
     */
    public function handle(PoolFinishedEvent $event): void
    {
        $pool = Pool::find($event->poolId);
        if (!$pool) {
            Log::error("PoolFinishedEventListener: Pool not found with ID: {$event->poolId}");
            return;
        }

        try {
            $this->historicalFightService->archivePoolFights($event->poolId);

            foreach ($pool->users as $user) {
                event(new SessionFinishedEvent($user->id, $pool));
            }

            $wallets = $pool->users->pluck('wallet_address')->toArray();
            Log::info("PoolFinishedEventListener: Pool ID {$event->poolId} has been processed successfully. Users: " . implode(', ', $wallets));
        } catch (\Exception $e) {
            $wallets = isset($pool) ? $pool->users->pluck('wallet_address')->toArray() : [];
            $walletStr = !empty($wallets) ? " Users: " . implode(', ', $wallets) : "";
            Log::error("Error processing PoolFinishedEvent for pool ID {$event->poolId}: " . $e->getMessage() . $walletStr);
        }
    }
}
