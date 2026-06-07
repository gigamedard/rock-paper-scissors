<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserCard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Periodically checks for recently expired time-based cards
 * and re-synchronizes user limits on the blockchain.
 *
 * This prevents the on-chain expiry from remaining at 9999999999
 * after session-based cards are consumed and time-based cards expire,
 * which would allow users to bypass limits by calling the smart contract directly.
 */
class SyncExpiredCardLimits extends Command
{
    protected $signature = 'cards:sync-expired-limits';
    protected $description = 'Re-sync blockchain limits for users whose cards have recently expired';

    public function handle(): int
    {
        // Find user_cards that expired within the last 10 minutes
        // and whose status is still 'available' (not yet cleaned up)
        $recentlyExpired = UserCard::with(['user', 'card'])
            ->where('status', 'available')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('expires_at', '>=', now()->subMinutes(10))
            ->get();

        if ($recentlyExpired->isEmpty()) {
            $this->info('No recently expired cards found.');
            return self::SUCCESS;
        }

        // Collect unique user IDs that need re-sync
        $userIds = $recentlyExpired->pluck('user_id')->unique();

        // Mark expired cards as consumed
        foreach ($recentlyExpired as $userCard) {
            $userCard->update(['status' => 'consumed']);
            Log::info("[CARD_EXPIRED] Card {$userCard->card_id} expired for user {$userCard->user_id}");
        }

        // Re-sync limits for each affected user
        $syncCount = 0;
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user && !empty($user->wallet_address)) {
                try {
                    $user->syncLimitsToBlockchain();
                    $syncCount++;
                    Log::info("[CARD_SYNC] Re-synced blockchain limits for user {$user->wallet_address}");
                } catch (\Exception $e) {
                    Log::error("[CARD_SYNC_ERROR] Failed to sync limits for user {$userId}: " . $e->getMessage());
                }
            }
        }

        $this->info("Processed {$recentlyExpired->count()} expired cards, re-synced {$syncCount} users.");
        return self::SUCCESS;
    }
}
