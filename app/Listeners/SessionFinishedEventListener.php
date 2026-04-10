<?php

namespace App\Listeners;

use App\Events\SessionFinishedEvent;
use App\Helpers\Web3Helper;
use App\Models\FHist;
use App\Models\User;
use App\Services\PinataService;
use Illuminate\Support\Facades\Log;

class SessionFinishedEventListener
{
    protected $web3Helper;

    protected $pinataService;

    protected $fightService;

    public function __construct(Web3Helper $web3Helper, PinataService $pinataService, \App\Services\FightService $fightService)
    {
        $this->web3Helper = $web3Helper;
        $this->pinataService = $pinataService;
        $this->fightService = $fightService;
    }

    /**
     * Handle the event.
     */
    public function handle(SessionFinishedEvent $event): void
    {
        try {
            $user = User::find($event->userId);
            if (! $user || ! $user->preMove) {
                return;
            }

            // Capture pool details from the event
            $pool = $event->pool;
            $baseBet = $pool ? $pool->base_bet : 0;
            $poolSize = $pool ? $pool->pool_size : 0;

            $q = $this->calculateQValue($user);
            Log::info("Calculated q: $q for User: {$user->id} (Wallet: {$user->wallet_address})");

            $this->processUserBalance($user, $q, $baseBet, $poolSize);
        } catch (\Exception $e) {
            $walletStr = isset($user) && $user ? " (Wallet: {$user->wallet_address})" : '';
            Log::error("SessionFinishedEventListener: Exception occurred for user ID: {$event->userId}{$walletStr} - ".$e->getMessage());
        }
    }

    private function calculateQValue(User $user): float
    {
        $initialTotal = $user->session_start_balance + $user->session_start_battle_balance;
        $finalTotal = $user->balance + $user->battle_balance;

        return $finalTotal / ($initialTotal ?: 1);
    }

    private function processUserBalance(User $user, float $q, float $baseBet, int $poolSize): void
    {
        UserTracker::info("SessionFinishedEventListener: processUserBalance started for user {$user->id} (Wallet: {$user->wallet_address}). Start Status: {$user->status}", ['wallet' => $user->wallet_address, 'status' => $user->status]);
        // If user is already available (waiting for batch) or stopped (insufficient funds), do not process session continuity
        if (in_array($user->status, ['available', 'stopped'])) {
            UserTracker::info("SessionFinishedEventListener: User {$user->id} (Wallet: {$user->wallet_address}) has status '{$user->status}'. Skipping immediate re-pool.", ['wallet' => $user->wallet_address]);

            return;
        }

        // Retrieve multiplier based on user level
        $multiplierLevel = $user->multiplier_level ?? 1;
        $multiplier = config("game_levels.multiplier.{$multiplierLevel}", 2.0);

        if ($q >= $multiplier) {
            Log::info("[SESSION_PAYOUT] 🏆 User {$user->id} (Wallet: {$user->wallet_address}) reached multiplier ($q >= $multiplier). Total Gained: {$user->balance} ETH. Processing withdrawal to Smart Contract!");
            $this->transferBattleBalance($user, 'stopped');
            $this->archiveSessionHistory($user);
            $this->sendPayment($user);

            // Set Cooldown
            $this->setNextSessionTime($user);
        } elseif ($q < 1 && $user->balance < $user->bet_amount) {
            UserTracker::info("[SESSION_CONTINUE] ⚠️ User {$user->id} (Wallet: {$user->wallet_address}) lost value (q=$q < 1) and balance ({$user->balance}) < bet_amount ({$user->bet_amount}). Returning to 'available' for Round Robin re-pooling at current bet tier.", ['wallet' => $user->wallet_address, 'q' => $q]);
            $user->status = 'available';
            $user->pool_id = null;
            $user->save();
        } else {
            // Threshold not reached, continue session
            // Set survivor to 'available' so run_batch_processor.js handles re-pooling
            UserTracker::info("[SESSION_CONTINUE] 🔄 User {$user->id} (Wallet: {$user->wallet_address}) did not reach multiplier ($q < $multiplier). Current Balance: {$user->balance}. Returning to pool waiting list.", ['wallet' => $user->wallet_address, 'q' => $q]);
            $user->status = 'available';
            $user->pool_id = null;
            $user->save();
        }
    }

    private function transferBattleBalance(User $user, string $newStatus = 'available'): void
    {
        $user->balance += $user->battle_balance;
        $user->pool_id = null;
        $user->battle_balance = 0;
        $user->bet_amount = 0;
        $user->preMove->current_index = 0;
        $user->status = $newStatus;
        $user->session_started = false;
        $user->session_start_balance = 0;
        $user->session_start_battle_balance = 0;
        $user->save();
    }

    private function archiveSessionHistory(User $user): void
    {
        $data = $this->getSessionFHists($user->id, $user->preMove->session_first_pool_id);
        if (empty($data)) {
            return;
        }

        \App\Jobs\UploadSessionHistoryJob::dispatch($user->wallet_address, $user->id, $data);
    }

    private function getSessionFHists($userId, $fstPoolId)
    {
        $fHistInitial = FHist::where(function ($query) use ($userId) {
            $query->where('user1_id', $userId)->orWhere('user2_id', $userId);
        })
            ->where('pool_id', $fstPoolId)
            ->orderBy('pool_id', 'asc')
            ->first();

        if (! $fHistInitial) {
            return [];
        }

        return FHist::where(function ($query) use ($userId, $fHistInitial) {
            $query->where('user1_id', $userId)->orWhere('user2_id', '>=', $fHistInitial->id);
        })->get();
    }

    private function sendPayment(User $user): void
    {
        $this->web3Helper->sendPayement(env('NODE_URL'), $user->wallet_address, $user->balance);
    }

    private function setNextSessionTime(User $user): void
    {
        $recoveryLevel = $user->recovery_level ?? 1;
        $minutes = config("game_levels.recovery_time.{$recoveryLevel}", 1440); // Default 24h
        $nextTime = now()->addMinutes($minutes)->timestamp;

        try {
            $this->web3Helper->setUserNextSessionTime(env('NODE_URL'), $user->wallet_address, $nextTime);
            Log::info("Set cooldown for User {$user->id} (Wallet: {$user->wallet_address}) until ".date('Y-m-d H:i:s', $nextTime));
        } catch (\Exception $e) {
            Log::error("Failed to set cooldown for User {$user->id} (Wallet: {$user->wallet_address}): ".$e->getMessage());
        }
    }
}
