<?php

namespace App\Listeners;

use App\Events\UserBalanceUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\HistoricalFightService;
use Illuminate\Support\Facades\Log;

class CheckBalanceThreshold implements ShouldQueue
{
    protected HistoricalFightService $historicalFightService;

    public function __construct(HistoricalFightService $historicalFightService)
    {
        $this->historicalFightService = $historicalFightService;
    }

    public function handle(UserBalanceUpdated $event)
    {
        
        Log::info('CheckBalanceThreshold listener triggered for user ID: . $event->user' );
        // Define the multiplier threshold (e.g., 3 times the join balance)
       // $multiplier = 3.0;
       // $this->historicalFightService->checkAndTriggerSmartContract($event->user, $multiplier);
    }
}