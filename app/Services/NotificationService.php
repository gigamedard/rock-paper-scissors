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
        Log::info("Notification: User {$user->id} won {$amount} in fight {$fightId}.");
        
        // Real-time Event
        event(new \App\Events\UserBalanceUpdated($user));
    }

    public function notifyFightStarted(User $user, User $opponent, $fight)
    {
        Log::info("Notification: User {$user->id} starting fight against {$opponent->id}.");
        
        // Real-time Event
        event(new \App\Events\FightStartedEvent($user, $opponent, $fight));
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

        // Real-time Event
        event(new \App\Events\PoolJoinedEvent($user, $pool));

        Log::info("Notification: {$message}");
    }

    /**
     * Notify user of insufficient balance to continue.
     *
     * @param User $user
     * @return void
     */
    public function notifyInsufficientBalance(User $user)
    {
        Log::info("Notification: User {$user->id} has insufficient balance to continue and has been stopped.");
        
        // Real-time Event
        event(new \App\Events\UserStoppedEvent($user, 'insufficient_funds'));
    }
}
