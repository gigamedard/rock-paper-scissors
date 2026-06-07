<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserCard;
use App\Helpers\Web3Helper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyCardPurchaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public UserCard $userCard;

    /**
     * Create a new job instance.
     */
    public function __construct(UserCard $userCard)
    {
        $this->userCard = $userCard;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->userCard->load(['user', 'card']);
        $user = $this->userCard->user;
        $card = $this->userCard->card;
        
        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');

        Log::info("Asynchronously verifying card purchase transaction", [
            'user_id' => $user->id,
            'tx_hash' => $this->userCard->tx_hash
        ]);

        $verifyResponse = app(Web3Helper::class)->verifySntTransfer(
            $nodeUrl,
            $this->userCard->tx_hash,
            $card->price,
            $user->wallet_address
        );

        if (isset($verifyResponse['success']) && $verifyResponse['success']) {
            $updateData = ['status' => 'available'];
            if ($card->duration_type === 'time') {
                $updateData['expires_at'] = now()->addHours($card->duration_value);
            }
            $this->userCard->update($updateData);
            Log::info("Card purchase verified and activated", ['user_card_id' => $this->userCard->id]);
            SyncUserLimitsJob::dispatch($user);
        } else {
            $this->userCard->update(['status' => 'failed']);
            Log::error("Card purchase verification failed", [
                'user_card_id' => $this->userCard->id,
                'response' => $verifyResponse
            ]);
        }
    }
}
