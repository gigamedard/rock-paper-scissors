<?php

namespace App\Services;

use App\Helpers\UserTracker;
use App\Helpers\Web3Helper;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;

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
        // Valider le parrainage si c'est le premier combat de l'utilisateur
        $referralService = app(\App\Services\ReferralService::class);
        $referralService->processReferralValidation($user);

        $baseBet = (float) ($user->initial_base_bet ?? \App\Models\GameSetting::getValue('min_bet_eth', collect(config('pool.base_bet', [0.01]))->min()));

        // --- CARD EFFECT: BASE BET MODIFIER ---
        $activeBaseBetCards = \App\Models\UserCard::with('card')
            ->where('user_id', $user->id)
            ->where('status', 'available')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('card', function ($q) {
                $q->where('effect_type', 'base_bet_modifier');
            })
            ->get();

        foreach ($activeBaseBetCards as $userCard) {
            $baseBet += $userCard->card->effect_value; // ex: +0.01 au base bet
        }

        // 1. Martingale Logic — 3 cases based on the pool outcome:
        //    - LOSS  : battle_balance < base_bet  → double the bet (Martingale escalation)
        //    - WIN   : battle_balance > base_bet  → reset bet_amount to base (0.01) per business rule
        //    - NULL  : battle_balance == base_bet → keep current tier (no change, user stays at same level)
        if ($user->battle_balance < $pool->base_bet) {
            // POOL LOSS: Martingale — double the next bet
            $nextBet = (float) ($user->bet_amount * 2);
            $maxLevel = (int) \App\Models\GameSetting::getValue('max_martingale_level', config('pool.max_martingale_level', 4));
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
        $multiplierLevel = $user->multiplier_level ?? 0;
        $multiplier = ($user->target_q && $user->target_q > 1.0) 
            ? $user->target_q 
            : config("game_levels.multiplier.{$multiplierLevel}", 2.0);

        // --- CARD EFFECT: CEILING INCREASE ---
        $activeCeilingCards = \App\Models\UserCard::with('card')
            ->where('user_id', $user->id)
            ->where('status', 'available')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('card', function ($q) {
                $q->where('effect_type', 'ceiling_increase');
            })
            ->get();

        foreach ($activeCeilingCards as $userCard) {
            $multiplier += $userCard->card->effect_value; // ex: +0.5 au plafond
        }

        if ($q >= $multiplier) {
            // CASE 1: Session Goal Reached (PAYOUT)
            Log::info("[SESSION_PAYOUT] 🏆 User {$user->id} (Wallet: {$user->wallet_address}) reached multiplier ($q >= $multiplier). Processing withdrawal!");
            
            // Calculate cooldown data first while the cards are still marked 'available'
            $cooldownData = $this->calculateCooldownData($user);

            $this->closeSession($user, 'stopped');
            $this->historyService->archiveSessionHistory($user);

            // CRITICAL: Sync limits to blockchain BEFORE setting cooldown.
            // The smart contract validates nextTime >= block.timestamp + getUserMinCooldown(user).
            // If we set the cooldown first, the contract still has stale limits and may reject.
            // Chain jobs to guarantee sequential execution:
            // SyncUserLimitsJob MUST complete before SetCooldownJob runs,
            // because the smart contract validates cooldown against current on-chain limits.
            $user->cooldown_until = \Carbon\Carbon::createFromTimestamp($cooldownData['nextTime']);
            $user->save();

            Bus::chain([
                new \App\Jobs\SyncUserLimitsJob($user),
                new \App\Jobs\SetCooldownJob($cooldownData['wallet'], $cooldownData['nextTime']),
            ])->dispatch();

            $signature = null;
            $payoutTriggered = false;
            $isBot = $this->isBotUser($user);

            if ($user->autoplay_active && $isBot) { 
                // ALL BOTS (Autoplay) get Automatic Payout
                $this->sendPayment($user);
                $payoutTriggered = true;
            } else {
                // HUMANS (including human autoplay) or Manual Players: Always generate Signature for MetaMask
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
            $isBot = $this->isBotUser($user);

            if ($user->autoplay_active && $isBot) {
                UserTracker::info("[BOT_AUTO_WITHDRAW] 🤖 Bot {$user->wallet_address} ruined. Forcing payout to clear balance for next run.", ['wallet' => $user->wallet_address]);
                $this->sendPayment($user); // Forces sending whatever is left (e.g. 0.45 ETH)
                $payoutTriggered = true;
            } else {
                if ($user->balance <= 0.0001) {
                    UserTracker::info("[AUTO_EJECT] 🛑 Human player {$user->wallet_address} ruined with 0 ETH. Server pays gas to eject them from contract.", ['wallet' => $user->wallet_address]);
                    $this->sendPayment($user); // Forces payout from server, clears isUserInAnyPool
                    $payoutTriggered = true;
                } else {
                    UserTracker::info("[MANUAL_WITHDRAWAL_REQUIRED] 🛑 Human player {$user->wallet_address} ruined. Funds ({$user->balance} ETH) kept in DB. Manual withdraw required.", ['wallet' => $user->wallet_address]);
                    $this->notificationService->notifyInsufficientBalance($user);
                    // Even on ruin, we provide the signature for the remaining funds
                    $signature = $this->generateHumanSignature($user);
                    $user->payout_signature = $signature;
                    $user->save();
                }
            }
            
            event(new \App\Events\SessionFinished($user, $type, (string)$q, $payoutTriggered, $signature));
            
        } else {
            // CASE 3: Session Continues (RETURN TO POOL QUEUE)
            UserTracker::info("[SESSION_CONTINUE] 🔄 User {$user->wallet_address} continues session (q=$q < $multiplier). Current Balance: {$user->balance}.", ['wallet' => $user->wallet_address]);
            
            $user->status = 'available';
            $user->save();
        }

        // Note: syncLimitsToBlockchain() is now called before setNextSessionCooldown()
        // in the PAYOUT case (CASE 1) to ensure on-chain limits are fresh.
        // For CASE 2 (ruin) and CASE 3 (continue), sync limits here.
        if ($q < $multiplier) {
            \App\Jobs\SyncUserLimitsJob::dispatch($user);
        }
    }

    private function closeSession(User $user, string $newStatus): void
    {
        $this->consumeActiveSessionCards($user);

        $user->status = $newStatus;
        // Reset bet_amount to the initial base bet so the bot can re-enter the arena
        // on its next session after a payout, ruin, or strategic limit.
        $baseBet = (float) ($user->initial_base_bet ?? \App\Models\GameSetting::getValue('min_bet_eth', collect(config('pool.base_bet', [0.01]))->min()));
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

    private function consumeActiveSessionCards(User $user): void
    {
        $activeSessionCards = \App\Models\UserCard::with('card')
            ->where('user_id', $user->id)
            ->where('status', 'available')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('card', function ($q) {
                $q->whereIn('effect_type', ['base_bet_modifier', 'ceiling_increase', 'cooldown_reduction']);
            })
            ->get();

        foreach ($activeSessionCards as $userCard) {
            $this->consumeCard($userCard);
        }
    }

    private function sendPayment(User $user): void
    {
        if (!empty($user->wallet_address)) {
            \App\Jobs\ProcessPayoutJob::dispatch($user->wallet_address, (float) $user->balance);
        }
    }

    /**
     * Calculate cooldown data without dispatching any jobs.
     * Used to prepare data for Bus::chain().
     */
    private function calculateCooldownData(User $user): array
    {
        $recoveryLevel = $user->recovery_level ?? 0;
        $minutes = config("game_levels.recovery_time.{$recoveryLevel}", 1440);

        $activeCooldownCards = \App\Models\UserCard::with('card')
            ->where('user_id', $user->id)
            ->where('status', 'available')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('card', function ($q) {
                $q->where('effect_type', 'cooldown_reduction');
            })
            ->get();

        foreach ($activeCooldownCards as $userCard) {
            $effectValue = $userCard->card->effect_value;
            if ($effectValue < 1) {
                $minutes = $minutes * (1 - $effectValue);
            } else {
                $minutes = max(0, $minutes - $effectValue);
            }
        }

        return [
            'wallet' => $user->wallet_address,
            'nextTime' => now()->addMinutes($minutes)->timestamp,
        ];
    }

    private function setNextSessionCooldown(User $user): void
    {
        $recoveryLevel = $user->recovery_level ?? 0;
        $minutes = config("game_levels.recovery_time.{$recoveryLevel}", 1440);

        // --- CARD EFFECT: COOLDOWN REDUCTION ---
        $activeCooldownCards = \App\Models\UserCard::with('card')
            ->where('user_id', $user->id)
            ->where('status', 'available')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('card', function ($q) {
                $q->where('effect_type', 'cooldown_reduction');
            })
            ->get()
            ->sortBy(function ($userCard) {
                // Apply percentage reductions first, then fixed values for determinism
                return $userCard->card && $userCard->card->effect_value < 1 ? 0 : 1;
            });

        foreach ($activeCooldownCards as $userCard) {
            $effectValue = $userCard->card->effect_value; // ex: 0.5 (50%) ou 60 (60 minutes)
            if ($effectValue < 1) {
                // Pourcentage (ex: 0.5 => -50%)
                $minutes = $minutes * (1 - $effectValue);
            } else {
                // Valeur fixe (ex: 60 => -60 mins)
                $minutes = max(0, $minutes - $effectValue);
            }
            $this->consumeCard($userCard);
        }

        $nextTime = now()->addMinutes($minutes)->timestamp;

        $user->cooldown_until = \Carbon\Carbon::createFromTimestamp($nextTime);
        $user->save();

        if (!empty($user->wallet_address)) {
            \App\Jobs\SetCooldownJob::dispatch($user->wallet_address, $nextTime);
        }
    }

    private function consumeCard(\App\Models\UserCard $userCard): void
    {
        if ($userCard->card->duration_type === 'sessions') {
            if ($userCard->remaining_sessions > 0) {
                $userCard->remaining_sessions -= 1;
                if ($userCard->remaining_sessions <= 0) {
                    $userCard->status = 'consumed';
                }
                $userCard->save();
            }
        } elseif ($userCard->card->duration_type === 'time') {
            if ($userCard->expires_at && now()->greaterThan($userCard->expires_at)) {
                $userCard->status = 'consumed';
                $userCard->save();
            }
        }
    }

    private function generateHumanSignature(User $user): ?string
    {
        try {
            $nodeUrl = config('app.NODE_WORKER_URL');
            $amountWei = $this->web3Helper->etherToWei($user->balance);

            // Delegate signature generation to the Node.js bridge which uses ethers.js signMessage()
            // This avoids the unreliable recovery parameter from the PHP Elliptic library.
            $response = \Illuminate\Support\Facades\Http::post("{$nodeUrl}/generate-signature", [
                'wallet' => $user->wallet_address,
                'amount' => $amountWei,
            ]);

            if ($response->successful() && $response->json('signature')) {
                return $response->json('signature');
            }

            Log::error("Bridge /generate-signature failed for {$user->wallet_address}: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to generate signature for {$user->wallet_address}: " . $e->getMessage());
            return null;
        }
    }

    private function isBotUser(User $user): bool
    {
        $jsonPath = base_path('smart_contracts/simulation_accounts.json');
        if (file_exists($jsonPath)) {
            $accounts = json_decode(file_get_contents($jsonPath), true);
            if (is_array($accounts)) {
                $address = strtolower($user->wallet_address);
                foreach ($accounts as $acc) {
                    if (isset($acc['address']) && strtolower($acc['address']) === $address) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
