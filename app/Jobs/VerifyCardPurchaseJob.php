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

        // Count and fetch all user cards sharing the same transaction hash to verify the total amount
        $allCardsWithSameTx = \App\Models\UserCard::where('tx_hash', $this->userCard->tx_hash)->get();
        $quantity = $allCardsWithSameTx->count();
        $expectedTotalAmount = $card->price * $quantity;

        Log::info("Asynchronously verifying card purchase transaction", [
            'user_id' => $user->id,
            'tx_hash' => $this->userCard->tx_hash,
            'quantity' => $quantity,
            'expected_total_amount' => $expectedTotalAmount
        ]);

        $verifyResponse = app(Web3Helper::class)->verifySntTransfer(
            $nodeUrl,
            $this->userCard->tx_hash,
            $expectedTotalAmount,
            $user->wallet_address
        );

        if (isset($verifyResponse['success']) && $verifyResponse['success']) {
            foreach ($allCardsWithSameTx as $uCard) {
                $updateData = ['status' => 'available'];
                if ($card->duration_type === 'time') {
                    $updateData['expires_at'] = now()->addHours($card->duration_value);
                }
                $uCard->update($updateData);
            }
            Log::info("Card purchase verified and activated for all quantity", [
                'tx_hash' => $this->userCard->tx_hash,
                'quantity' => $quantity
            ]);
            SyncUserLimitsJob::dispatch($user);
        } else {
            \App\Models\UserCard::where('tx_hash', $this->userCard->tx_hash)->update(['status' => 'failed']);
            Log::error("Card purchase verification failed", [
                'tx_hash' => $this->userCard->tx_hash,
                'response' => $verifyResponse
            ]);
        }
    }
}
