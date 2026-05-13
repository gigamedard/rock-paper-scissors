<?php

namespace App\Services;

use App\Helpers\UserTracker;
use App\Helpers\Web3Helper;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SessionManager
{
    protected NotificationService $notificationService;
    protected SignatureService $signatureService;

    public function __construct(Web3Helper $web3Helper, SessionHistoryService $historyService, NotificationService $notificationService, SignatureService $signatureService)
    {
        $this->web3Helper = $web3Helper;
        $this->historyService = $historyService;
        $this->notificationService = $notificationService;
        $this->signatureService = $signatureService;
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
        $baseBet = (float) collect(config('pool.base_bet', [0.01]))->min();

        // 1. Martingale Logic — 3 cases based on the pool outcome:
        //    - LOSS  : battle_balance < base_bet  → double the bet (Martingale escalation)
        //    - WIN   : battle_balance > base_bet  → reset bet_amount to base (0.01) per business rule
        //    - NULL  : battle_balance == base_bet → keep current tier (no change, user stays at same level)
        if ($user->battle_balance < $pool->base_bet) {
            // POOL LOSS: Martingale — double the next bet
            $nextBet = (float) ($user->bet_amount * 2);
            $maxLevel = (int) config('pool.max_martingale_level', 4);
            $maxMartingaleAmount = (float) ($baseBet * pow(2, $maxLevel));

            if ($nextBet > $maxMartingaleAmount) {
                // Global Limit reached: Reset to base bet
                $user->bet_amount = $baseBet;
                UserTracker::info("[MARTINGALE_LIMIT] 🛡️ Player {$user->wallet_address} reached global martingale limit level {$maxLevel} ({$nextBet} > {$maxMartingaleAmount}). Resetting to base bet: {$baseBet}.", ['wallet' => $user->wallet_address]);
            } else {
                // Escalation: Double the bet
                $user->bet_amount = $nextBet;
                UserTracker::info("[MARTINGALE] 📉 Player {$user->wallet_address} lost pool. Next bet doubled to: {$user->bet_amount}.", ['wallet' => $user->wallet_address, 'next_bet' => $user->bet_amount]);
            }
            event(new \App\Events\MartingaleUpdated($user, (string)$user->bet_amount));

        } elseif ($user->battle_balance > $pool->base_bet) {
            // POOL WIN: Reset bet_amount to base bet (0.01) — business rule
            $user->bet_amount = $baseBet;
            UserTracker::info("[BET_RESET] ✅ Player {$user->wallet_address} won pool. bet_amount reset to base: {$baseBet}.", ['wallet' => $user->wallet_address, 'next_bet' => $baseBet]);

        } else {
            // POOL NULL (battle_balance == base_bet): Stay at current tier — no change
            UserTracker::info("[POOL_NULL] ↔️ Player {$user->wallet_address} drew pool (null). bet_amount unchanged: {$user->bet_amount}.", ['wallet' => $user->wallet_address, 'current_bet' => $user->bet_amount]);
        }

        // 2. Calculate Q and Evaluate Session State
        $q = $this->calculateQValue($user);

        // 3. Consolidate funds from battle back to main balance
        $gained = $user->battle_balance;
        
        \App\Helpers\UserTracker::info("[POOL_FINISH] 💰 Battle phase ended for Player {$user->wallet_address}. Gained: {$gained}. Total Internal Balance: " . ($user->balance + $user->battle_balance), ['wallet' => $user->wallet_address]);

        $user->balance += $user->battle_balance;
        $user->battle_balance = 0;
        $user->pool_id = null;

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
            $this->setNextSessionCooldown($user);

            $signature = null;
            $payoutTriggered = false;

            if ($user->autoplay_active) { 
                // ALL BOTS (Autoplay) get Automatic Payout
                $this->sendPayment($user);
                $payoutTriggered = true;
            } else {
                // HUMANS (ID < 100) or Manual Players: Always generate Signature for MetaMask
                $signature = $this->generateHumanSignature($user);
                $user->payout_signature = $signature;
                $user->save();
            }
            
            event(new \App\Events\SessionFinished($user, "SUCCESS", (string)$q, $payoutTriggered, $signature));
            
        } elseif (($q < 1 && $user->balance < $user->bet_amount) || ($q >= 1 && $user->balance < $user->bet_amount)) {
            // CASE 2: Ruin or Strategic Limit (Insufficient funds for next bet)
            $type = ($q < 1) ? "RUIN" : "STRATEGIC_LIMIT";
            UserTracker::info("[SESSION_{$type}] ⚠️ User {$user->wallet_address} (q=$q) cannot cover next bet ({$user->balance} < {$user->bet_amount}). Session ended.", ['wallet' => $user->wallet_address, 'q' => $q]);
            
            $this->closeSession($user, 'stopped');
            $this->historyService->archiveSessionHistory($user);
            
            // DIVERGENCE: Bots get auto-payout for tests, Humans must manually withdraw
            $payoutTriggered = false;
            $signature = null;

            if ($user->autoplay_active) {
                UserTracker::info("[BOT_AUTO_WITHDRAW] 🤖 Bot {$user->wallet_address} ruined. Forcing payout to clear balance for next run.", ['wallet' => $user->wallet_address]);
                $this->sendPayment($user); // Forces sending whatever is left (e.g. 0.45 ETH)
                $payoutTriggered = true;
            } else {
                UserTracker::info("[MANUAL_WITHDRAWAL_REQUIRED] 🛑 Human player {$user->wallet_address} ruined. Funds ({$user->balance} ETH) kept in DB. Manual withdraw required.", ['wallet' => $user->wallet_address]);
                $this->notificationService->notifyInsufficientBalance($user);
                // Even on ruin, we provide the signature for the remaining funds
                $signature = $this->generateHumanSignature($user);
                $user->payout_signature = $signature;
                $user->save();
            }
            
            event(new \App\Events\SessionFinished($user, $type, (string)$q, $payoutTriggered, $signature));
            
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
        // Reset bet_amount to the base bet (0.01) so the bot can re-enter the arena
        // on its next session after a payout, ruin, or strategic limit.
        $baseBet = (float) collect(config('pool.base_bet', [0.01]))->min();
        $user->bet_amount = $baseBet;
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
            $this->web3Helper->sendPayement(config('app.NODE_WORKER_URL'), $user->wallet_address, $user->balance);
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
            $this->web3Helper->setUserNextSessionTime(config('app.NODE_WORKER_URL'), $user->wallet_address, $nextTime);
        } catch (\Exception $e) {
            Log::error("Failed to set cooldown for {$user->wallet_address}: " . $e->getMessage());
        }
    }

    private function generateHumanSignature(User $user): ?string
    {
        try {
            $nodeUrl = config('app.NODE_WORKER_URL');
            $contractAddress = env('BATTLEPOOL_ADDRESS');
            $nonce = $this->web3Helper->getUserNonce($nodeUrl, $user->wallet_address);
            $amountWei = $this->web3Helper->etherToWei($user->balance);

            return $this->signatureService->generateClaimSignature(
                $user->wallet_address,
                $amountWei,
                $nonce,
                $contractAddress
            );
        } catch (\Exception $e) {
            Log::error("Failed to generate signature for {$user->wallet_address}: " . $e->getMessage());
            return null;
        }
    }
}
