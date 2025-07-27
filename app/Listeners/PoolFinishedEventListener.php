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

        // Archive the entire pool's fight history and send it to Pinata.
        app(HistoricalFightService::class)->archivePoolFights($poolId);

        // Get the users who are still in the pool (the winners).
        $users = $pool->users()->get();

        // For each remaining user, fire an event to signal their session is finished.
        // The SessionFinishedEventListener will handle cashing out their battle_balance and resetting their status.
        foreach ($users as $user) {
            event(new SessionFinishedEvent($user->id));
        }

  
        //$pool->delete();

        Log::info("PoolFinishedEventListener: Pool ID $poolId has been processed successfully.");
    }
}
