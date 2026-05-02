<?php

namespace App\Services;

use App\Events\SessionFinishedEvent;
use App\Helpers\UserTracker;
use App\Helpers\Web3Helper;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SessionManager
{
    protected Web3Helper $web3Helper;
    protected SessionHistoryService $historyService;
    protected NotificationService $notificationService;

    public function __construct(Web3Helper $web3Helper, SessionHistoryService $historyService, NotificationService $notificationService)
    {
        $this->web3Helper = $web3Helper;
        $this->historyService = $historyService;
        $this->notificationService = $notificationService;
    }

    /**
     * Executes the end-of-pool logic for all participants.
     */
    public function evaluatePoolEnd(Pool $pool): void
    {
        // 1. Identify users who fought vs users who were left out
        $fightUserIds = Fight::where('pool_id', $pool->id)
            ->get()
            ->flatMap(fn ($fight) => [$fight->user1_id, $fight->user2_id])
            ->unique()
            ->values();

        $foughtUsers = User::whereIn('id', $fightUserIds)->get();

        $noFightUserIds = $pool->users()
            ->where('battle_balance', '>', 0)
            ->pluck('id')
            ->diff($fightUserIds);

        $noFightUsers = User::whereIn('id', $noFightUserIds)->get();

        // 2. Refund users who didn't fight
        foreach ($noFightUsers as $user) {
            $this->refundNoFightUser($user);
        }

        // 3. Evaluate session for users who fought
        foreach ($foughtUsers as $user) {
            $this->processFoughtUser($user, $pool);
        }
    }

    private function refundNoFightUser(User $user): void
    {
        $refunded = $user->battle_balance;
        $user->balance += $refunded;
        $user->battle_balance = 0;
        
        $user->status = 'available';
        $user->pool_id = null;
        $user->save();

        UserTracker::info("[POOL_FINISH] 🔄 Player {$user->wallet_address} had no fight. Refunded {$refunded} ETH. Balance restored to: {$user->balance}.", ['wallet' => $user->wallet_address]);
    }

    private function processFoughtUser(User $user, Pool $pool): void
    {
        // 1. Martingale Logic: Double the bet if the user lost their battle_balance in this pool
        if ($user->battle_balance < $pool->base_bet) {
            $user->bet_amount = $user->bet_amount * 2;
            UserTracker::info("[MARTINGALE] 📉 Player {$user->wallet_address} lost. Next bet doubled to: {$user->bet_amount}.", ['wallet' => $user->wallet_address, 'next_bet' => $user->bet_amount]);
        }

        // 2. Calculate Q and Evaluate Session State
        $q = $this->calculateQValue($user);
        
        // 3. Consolidate funds from battle back to main balance
        $gained = $user->battle_balance;
        $user->balance += $user->battle_balance;
        $user->battle_balance = 0;
        $user->pool_id = null;

        UserTracker::info("[POOL_FINISH] 💰 Battle phase ended for Player {$user->wallet_address}. Gained: {$gained}. Total Internal Balance: {$user->balance}.", ['wallet' => $user->wallet_address]);

        $this->evaluateUserSession($user, $q);
    }

    private function calculateQValue(User $user): float
    {
        $initialTotal = $user->session_start_balance + $user->session_start_battle_balance;
        $finalTotal = $user->balance + $user->battle_balance;

        if ($initialTotal <= 0) {
            Log::warning("[SESSION_GUARD] ⚠️ calculateQValue called with initialTotal=0 for user {$user->wallet_address}. Returning q=0 to prevent illegitimate payout.", [
                'wallet' => $user->wallet_address,
                'balance' => $user->balance,
                'battle_balance' => $user->battle_balance,
                'session_start_balance' => $user->session_start_balance,
                'session_start_battle_balance' => $user->session_start_battle_balance,
            ]);
            return 0.0;
        }

        return $finalTotal / $initialTotal;
    }

    private function evaluateUserSession(User $user, float $q): void
    {
        $multiplierLevel = $user->multiplier_level ?? 1;
        $multiplier = config("game_levels.multiplier.{$multiplierLevel}", 2.0);

        if ($q >= $multiplier) {
            // CASE 1: Session Goal Reached (PAYOUT)
            Log::info("[SESSION_PAYOUT] 🏆 User {$user->id} (Wallet: {$user->wallet_address}) reached multiplier ($q >= $multiplier). Processing withdrawal!");
            
            $this->closeSession($user, 'stopped');
            $this->historyService->archiveSessionHistory($user);
            $this->sendPayment($user);
            $this->setNextSessionCooldown($user);
            
        } elseif (($q < 1 && $user->balance < $user->bet_amount) || ($q >= 1 && $user->balance < $user->bet_amount)) {
            // CASE 2: Ruin or Strategic Limit (Insufficient funds for next bet)
            $type = ($q < 1) ? "RUIN" : "STRATEGIC_LIMIT";
            UserTracker::info("[SESSION_{$type}] ⚠️ User {$user->wallet_address} (q=$q) cannot cover next bet ({$user->balance} < {$user->bet_amount}). Session ended.", ['wallet' => $user->wallet_address, 'q' => $q]);
            
            $this->closeSession($user, 'stopped');
            $this->historyService->archiveSessionHistory($user);
            $this->notificationService->notifyInsufficientBalance($user);
            
        } else {
            // CASE 3: Session Continues (RETURN TO POOL QUEUE)
            UserTracker::info("[SESSION_CONTINUE] 🔄 User {$user->wallet_address} continues session (q=$q < $multiplier). Current Balance: {$user->balance}.", ['wallet' => $user->wallet_address]);
            
            $user->status = 'available';
            $user->save();
        }
    }

    private function closeSession(User $user, string $newStatus): void
    {
        $user->status = $newStatus;
        $user->bet_amount = 0;
        if ($user->preMove) {
            $user->preMove->current_index = 0;
            $user->preMove->save();
        }
        $user->session_started = false;
        $user->session_start_balance = 0;
        $user->session_start_battle_balance = 0;
        $user->save();
    }

    private function sendPayment(User $user): void
    {
        try {
            $this->web3Helper->sendPayement(env('NODE_URL'), $user->wallet_address, $user->balance);
        } catch (\Exception $e) {
            Log::error("Failed to send payment for {$user->wallet_address}: " . $e->getMessage());
        }
    }

    private function setNextSessionCooldown(User $user): void
    {
        $recoveryLevel = $user->recovery_level ?? 1;
        $minutes = config("game_levels.recovery_time.{$recoveryLevel}", 1440);
        $nextTime = now()->addMinutes($minutes)->timestamp;

        try {
            $this->web3Helper->setUserNextSessionTime(env('NODE_URL'), $user->wallet_address, $nextTime);
        } catch (\Exception $e) {
            Log::error("Failed to set cooldown for {$user->wallet_address}: " . $e->getMessage());
        }
    }
}
