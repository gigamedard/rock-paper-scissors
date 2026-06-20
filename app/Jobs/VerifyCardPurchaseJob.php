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

        // Fetch all user cards sharing this transaction hash (with or without suffix) to verify the total amount
        $baseTxHash = $this->userCard->tx_hash;
        if (preg_match('/^(0x[a-fA-F0-9]+)_qty_\d+$/', $baseTxHash, $matches)) {
            $baseTxHash = $matches[1];
        }

        $allCardsWithSameTx = \App\Models\UserCard::where('tx_hash', $baseTxHash)
            ->orWhere('tx_hash', 'like', $baseTxHash . '_qty_%')
            ->get();

        $quantity = $allCardsWithSameTx->count();
        $expectedTotalAmount = $card->price * $quantity;

        Log::info("Asynchronously verifying card purchase transaction", [
            'user_id' => $user->id,
            'tx_hash' => $baseTxHash,
            'quantity' => $quantity,
            'expected_total_amount' => $expectedTotalAmount
        ]);

        $verifyResponse = app(Web3Helper::class)->verifySntTransfer(
            $nodeUrl,
            $baseTxHash,
            $expectedTotalAmount,
            $user->wallet_address
        );

        if (isset($verifyResponse['success']) && $verifyResponse['success']) {
            // Store old limits to check for retroactive cooldown reduction
            $oldLimits = $user->getActiveLimits();

            foreach ($allCardsWithSameTx as $uCard) {
                $updateData = ['status' => 'available'];
                if ($card->duration_type === 'time') {
                    $updateData['expires_at'] = now()->addHours($card->duration_value);
                }
                $uCard->update($updateData);
            }

            // Calculate new limits after cards are active
            $user->refresh();
            $newLimits = $user->getActiveLimits();
            $deltaMinutes = $oldLimits['min_cooldown'] - $newLimits['min_cooldown'];

            if ($deltaMinutes > 0 && $user->cooldown_until && \Carbon\Carbon::parse($user->cooldown_until)->isFuture()) {
                $user->cooldown_until = \Carbon\Carbon::parse($user->cooldown_until)->subMinutes($deltaMinutes);
                
                // Si le cooldown est maintenant dans le passé, on peut carrément nettoyer le champ
                if ($user->cooldown_until->isPast()) {
                    $user->cooldown_until = null;
                }
                $user->save();

                Log::info("Retroactive cooldown applied", [
                    'user_id' => $user->id,
                    'reduced_by_minutes' => $deltaMinutes,
                    'new_cooldown_until' => $user->cooldown_until
                ]);

                // Sync the retroactive cooldown directly to the blockchain
                try {
                    app(Web3Helper::class)->setUserNextSessionTime(
                        $nodeUrl,
                        $user->wallet_address,
                        $user->cooldown_until ? \Carbon\Carbon::parse($user->cooldown_until)->timestamp : 0
                    );
                } catch (\Exception $e) {
                    Log::error("Failed to sync retroactive cooldown to blockchain", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info("Card purchase verified and activated for all quantity", [
                'tx_hash' => $baseTxHash,
                'quantity' => $quantity
            ]);
            SyncUserLimitsJob::dispatch($user);
        } else {
            \App\Models\UserCard::where('tx_hash', $baseTxHash)
                ->orWhere('tx_hash', 'like', $baseTxHash . '_qty_%')
                ->update(['status' => 'failed']);
            Log::error("Card purchase verification failed", [
                'tx_hash' => $baseTxHash,
                'response' => $verifyResponse
            ]);
        }
    }
}
