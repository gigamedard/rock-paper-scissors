<?php

namespace App\Listeners;

use App\Events\PoolFinishedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Models\Pool;
use App\Events\SessionFinishedEvent;
use App\Services\HistoricalFightService;

class PoolFinishedEventListener
{


    /**
     * Handle the event.
     */
    public function handle(PoolFinishedEvent $event): void
    {
        // Get the pool ID from the event
        $poolId = $event->poolId;

        // Get the pool from the database
        $pool = Pool::find($poolId);
        if (!$pool) {
            Log::error("PoolFinishedEventListener: Pool not found with ID: $poolId");
            return;
        }

        // Get the pool's users
        $users = $pool->users()->get();

        // Transfer each user's battle_balance to their main balance and remove from the pool
        foreach ($users as $user) {
            event(new SessionFinishedEvent($user->id));
            $user->pool_id = null;
            $user->balance += $user->battle_balance;
            $user->battle_balance = 0;
            $user->status = 'available';
            $user->save();
           
        }

        // Archive the pool's fights
        //$historicalFightService = new HistoricalFightService();
        //$historicalFightService->archivePoolFights($poolId);

        app(HistoricalFightService::class)->archivePoolFights($poolId);

  
        //$pool->delete();

        Log::info("PoolFinishedEventListener: Pool ID $poolId has been processed successfully.");
    }
}
