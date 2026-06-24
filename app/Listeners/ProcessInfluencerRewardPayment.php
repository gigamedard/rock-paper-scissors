<?php

namespace App\Listeners;

use App\Events\InfluencerRewardClaimed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Helpers\Web3Helper;
use Illuminate\Support\Facades\Log;

class ProcessInfluencerRewardPayment implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InfluencerRewardClaimed $event): void
    {
        $influencer = $event->influencer;
        $rewardAmount = $event->amount;

        // Retrieve wallet address
        $user = $influencer->user;
        $walletAddress = $user->wallet_address;

        if (!$walletAddress) {
            Log::error("InfluencerRewardPayment: User {$user->id} does not have a wallet address.");
            return;
        }

        $nodeUrl = env('NODE_WORKER_URL', 'http://localhost:3000');

        try {
            Log::info("Sending {$rewardAmount} AVAX to influencer {$user->id} (Wallet: {$walletAddress})");
            
            // Send the payment via Web3Helper
            $response = Web3Helper::sendPayement($nodeUrl, $walletAddress, $rewardAmount);
            
            Log::info("Influencer payment sent successfully.", ['response' => $response]);

        } catch (\Exception $e) {
            Log::error("InfluencerRewardPayment: Failed to send payment for influencer {$user->id}. Error: " . $e->getMessage());
            // Depending on business logic, we could potentially revert the 'has_claimed' status here
            // or just let it fail and retry via Laravel Horizon / queue jobs
            throw $e;
        }
    }
}
