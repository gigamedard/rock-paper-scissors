<?php

namespace App\Services;

use App\Models\User;
use App\Models\Pool;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notify user of a fight win.
     *
     * @param User $user
     * @param float $amount
     * @param int|string $fightId
     * @return void
     */
    public function notifyFightWin(User $user, float $amount, $fightId)
    {
        // Placeholder for real notification logic (e.g. database, websocket, email)
        Log::info("Notification: User {$user->id} won {$amount} in fight {$fightId}.");
    }

    /**
     * Notify user of entering a pool.
     *
     * @param User $user
     * @param Pool $pool
     * @param string $reason 'new_session', 'after_defeat', 'pool_winner'
     * @return void
     */
    public function notifyPoolEntry(User $user, Pool $pool, string $reason)
    {
        $message = "";
        switch ($reason) {
            case 'new_session':
                $message = "User {$user->id} started a new session in pool {$pool->id}.";
                break;
            case 'after_defeat':
                $message = "User {$user->id} joined pool {$pool->id} after a defeat.";
                break;
            case 'pool_winner':
                $message = "User {$user->id} joined pool {$pool->id} as a winner of the previous pool.";
                break;
            default:
                $message = "User {$user->id} joined pool {$pool->id}. Reason: {$reason}";
        }

        // Placeholder for real notification logic
        Log::info("Notification: {$message}");
    }
}
