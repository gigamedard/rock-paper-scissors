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
    protected Web3Helper $web3Helper;
    protected SessionHistoryService $historyService;
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

    /**
     * Recovers users left stuck in 'in_pool' when a pool's end evaluation
     * aborted (e.g. a failed synchronous broadcast). Only touches users still
     * attached to the given pool, so it is safe to re-run.
     */
    public function recoverStuckUsersInPool(Pool $pool): int
    {
        $stuckUsers = User::where('status', 'in_pool')
            ->where('pool_id', $pool->id)
            ->get();

        if ($stuckUsers->isEmpty()) {
            return 0;
        }

        foreach ($stuckUsers as $user) {
            $this->processFoughtUser($user, $pool);
            Log::info("[POOL_RECOVERY] 🔧 User {$user->id} recovered from stuck in_pool state for pool {$pool->id}. Status: {$user->status}.");
        }

        return $stuckUsers->count();
    }

    private function refundNoFightUser(User $user): void
    {
        $refunded = $user->battle_balance;
        $user->balance += $refunded;
        $user->battle_balance = 0;
        
        $user->status = 'available';
        $user->pool_id = null;
        $user->max_balance = max((float) ($user->max_balance ?? 0), (float) $user->balance);
        $user->min_balance = $user->min_balance === null ? (float) $user->balance : min((float) $user->min_balance, (float) $user->balance);

        // P3 : dépôt reçu pendant le pool → réinitialiser depuis le total on-chain.
        if ($user->pending_bankroll_total !== null) {
            try {
                app(\App\Services\BankrollSessionService::class)->applyPending($user);
            } catch (\Throwable $e) {
                Log::warning('[P3] pending reinit failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        $user->save();

        UserTracker::info("[POOL_FINISH] 🔄 Player {$user->wallet_address} had no fight. Refunded {$refunded} ETH. Balance restored to: {$user->balance}.", ['wallet' => $user->wallet_address]);
    }

    private function processFoughtUser(User $user, Pool $pool): void
    {
        // GUARD (fix claim disparu) : ne pas ré-évaluer un joueur qui a déjà
        // atteint son target (claim en attente OU cooldown actif). Sinon une
        // 2e évaluation (recoverStuckUsersInPool / race de ré-enrôlement) avec
        // session_start_balance=0 (SESSION_GUARD q=0) retomberait en 'available'
        // et écraserait le statut 'stopped' → le bouton claim disparaît.
        if ($user->payout_signature || ($user->cooldown_until && $user->cooldown_until->isFuture())) {
            return;
        }

        // Fast-path simulation bots : pas de cartes / logs / broadcasts / GameSetting DB.
        if ($user->autoplay_active && config('game_engine.simulation_no_payout', false)) {
            $this->processFoughtUserSim($user, $pool);
            return;
        }

        // NOTE: la validation de parrainage se fait désormais à la PREMIÈRE SESSION
        // (voir PoolReconstructionService::initializeUsersForPool) et non plus au premier combat.

        $baseBet = (float) ($user->initial_base_bet ?? \App\Models\GameSetting::getValue('min_bet_eth', collect(config('pool.base_bet', [0.0004]))->min()));

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
            \App\Helpers\BroadcastHelper::safe(fn() => event(new \App\Events\MartingaleUpdated($user, (string)$user->bet_amount)), "MartingaleUpdated user {$user->id}");
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

        // Suivi min/max de la balance (analyse a posteriori des cas de ruine).
        $user->max_balance = max((float) ($user->max_balance ?? 0), (float) $user->balance);
        $user->min_balance = $user->min_balance === null ? (float) $user->balance : min((float) $user->min_balance, (float) $user->balance);

        // P3 : dépôt reçu pendant le pool → nouvelle session depuis le total on-chain.
        if ($user->pending_bankroll_total !== null) {
            try {
                if (app(\App\Services\BankrollSessionService::class)->applyPending($user)) {
                    $q = 1.0;
                }
            } catch (\Throwable $e) {
                Log::warning('[P3] pending reinit failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        $this->evaluateUserSession($user, $q);
    }

    /**
     * Fast-path bots (simulation) : évite les requêtes cartes/GameSetting, les logs
     * et les broadcasts. Conserve la logique de session P3 (rattrapage/refund).
     */
    private function processFoughtUserSim(User $user, Pool $pool): void
    {
        $baseBet = (float) ($user->initial_base_bet ?? 0.0004);

        if ($user->battle_balance < $pool->base_bet) {
            $maxLevel = (int) config('pool.max_martingale_level', 4);
            $maxAmount = $baseBet * (2 ** $maxLevel);
            $next = (float) $user->bet_amount * 2;
            $user->bet_amount = ($next > $maxAmount) ? $baseBet : $next;
        } elseif ($user->battle_balance > $pool->base_bet) {
            $user->bet_amount = $baseBet;
        }

        $q = $this->calculateQValue($user);

        $user->balance += $user->battle_balance;
        $user->battle_balance = 0;
        $user->pool_id = null;
        $user->max_balance = max((float) ($user->max_balance ?? 0), (float) $user->balance);
        $user->min_balance = $user->min_balance === null ? (float) $user->balance : min((float) $user->min_balance, (float) $user->balance);

        // P3 : dépôt reçu pendant le pool → nouvelle session depuis le total on-chain.
        if ($user->pending_bankroll_total !== null) {
            try {
                if (app(\App\Services\BankrollSessionService::class)->applyPending($user)) {
                    $q = 1.0;
                }
            } catch (\Throwable $e) {
                Log::warning('[P3] pending reinit failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

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

    /**
     * Taux de frais contrat (en %) utilisé pour compenser le seuil Q.
     * Source prioritaire : GameSetting 'smart_contract_fee_percentage',
     * puis config('economy.fee_percent'), puis 2.5.
     */
    private function feePercent(): float
    {
        $fee = \App\Models\GameSetting::getValue(
            'smart_contract_fee_percentage',
            config('economy.fee_percent', 2.5)
        );

        return max(0.0, (float) $fee);
    }

    private function evaluateUserSession(User $user, float $q): void
    {
        // Mode simulation : bots sans payout on-chain / cooldown / notif.
        $sim = $user->autoplay_active && config('game_engine.simulation_no_payout', false);

        $multiplierLevel = $user->multiplier_level ?? 0;

        if ($user->target_q && $user->target_q > 1.0) {
            // Seuil effectif compensé des frais : le joueur doit réellement
            // gagner target_q x sa mise (frais inclus). Sim bots : fee depuis la
            // config (évite une requête GameSetting par joueur).
            $feePercent = $sim ? ((float) config('bankroll.fee_rate', 0.025) * 100) : $this->feePercent();
            $multiplier = $user->target_q * (1 + ($feePercent / 100));
        } else {
            $multiplier = config("game_levels.multiplier.{$multiplierLevel}", 2.0);
        }

        // --- CARD EFFECT: CEILING INCREASE --- (humains uniquement)
        if (! $sim) {
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
        }

        if ($q >= $multiplier) {
            // CASE 1: Session Goal Reached (PAYOUT)
            Log::info("[SESSION_PAYOUT] 🏆 User {$user->id} (Wallet: {$user->wallet_address}) reached multiplier ($q >= $multiplier). Processing withdrawal!");

            // P3 : session gagnée -> on restitue au solde la réserve non utilisée.
            if (config('bankroll.enabled') && $user->bankroll_total !== null) {
                $user->balance += (float) $user->bankroll_remaining;
                $user->bankroll_remaining = 0;
                $user->bankroll_total = null;
                $user->session_attempt = 0;
                $user->save();
            }

            // Mode simulation : pas de cooldown/payout/signature/on-chain.
            if ($sim) {
                $this->closeSession($user, 'stopped');
                return;
            }
            
            // Calculate cooldown data first while the cards are still marked 'available'
            $cooldownData = $this->calculateCooldownData($user);

            // Set cooldown BEFORE closeSession to avoid race condition with handleClaim
            $user->cooldown_until = \Carbon\Carbon::createFromTimestamp($cooldownData['nextTime']);
            $user->save();

            $this->closeSession($user, 'stopped');
            if (! $this->deferSideEffect('session_history', $user->id)) {
                $this->historyService->archiveSessionHistory($user);
            }

            // Sync limits (délégué à l'outbox si activé, sinon synchrone).
            if (! $this->deferSideEffect('sync_limits', $user->id)) {
                try {
                    $user->syncLimitsToBlockchain();
                } catch (\Exception $e) {
                    Log::error("[SESSION_PAYOUT] Failed to sync limits for user {$user->id}: " . $e->getMessage());
                }
            }

            // Cooldown (délégué à l'outbox si activé).
            if (! $this->deferSideEffect('cooldown', $user->id, ['wallet' => $cooldownData['wallet'], 'next_time' => $cooldownData['nextTime']])) {
                \App\Jobs\SetCooldownJob::dispatch($cooldownData['wallet'], $cooldownData['nextTime']);
            }

            $signature = null;
            $payoutTriggered = false;
            $isBot = $this->isBotUser($user);

            if ($user->autoplay_active && $isBot) { 
                // ALL BOTS (Autoplay) get Automatic Payout (outbox si activé)
                $this->deferPayout($user);
                $payoutTriggered = true;
            } else {
                // HUMANS (including human autoplay) or Manual Players: Always generate Signature for MetaMask
                $sigData = $this->generateHumanSignature($user);
                $signature = $sigData['signature'] ?? null;
                $user->payout_signature = $signature;
                $user->payout_deadline = $sigData['deadline'] ?? null;
                $user->save();
            }
            
            if (! $this->deferSideEffect('session_finished', $user->id, ['status' => 'SUCCESS', 'q' => (string) $q, 'payout_triggered' => $payoutTriggered, 'signature' => $signature])) {
                \App\Helpers\BroadcastHelper::safe(fn() => event(new \App\Events\SessionFinished($user, "SUCCESS", (string)$q, $payoutTriggered, $signature)), "SessionFinished user {$user->id} SUCCESS");
            }
            
        } elseif ($user->balance < $user->bet_amount) {
            // CASE 2: Ruin or Strategic Limit (Insufficient funds for next bet)
            $type = ($q < 1) ? "RUIN" : "STRATEGIC_LIMIT";
            $sessionBaseBet = (float) ($user->initial_base_bet ?? \App\Models\GameSetting::getValue('min_bet_eth', collect(config('pool.base_bet', [0.01]))->min()));

            if ($user->balance >= $sessionBaseBet) {
                // Solde résiduel >= mise de base → la session CONTINUE (on ne la ferme PAS, on ne la
                // réinitialise PAS). Le joueur est simplement réintégré au palier de base et continue
                // à jouer tant que balance >= base. Ainsi session_start_balance reste l'original (0.4)
                // et doubler un solde minuscule ne déclenche PAS un faux succès.
                UserTracker::info("[SESSION_{$type}] ♻️ User {$user->wallet_address} (q=$q) cannot cover tier bet ({$user->balance} < {$user->bet_amount}) but can continue at base ({$sessionBaseBet}). Session kept, retry at base.", ['wallet' => $user->wallet_address, 'q' => $q, 'balance' => $user->balance, 'base_bet' => $sessionBaseBet]);
                $user->status = 'available';
                $user->bet_amount = $sessionBaseBet;
                if ($user->preMove) {
                    $user->preMove->current_index = 0;
                    $user->preMove->save();
                }
                $user->save();
            } else {
                // Vraie ruine : arrêt + payout (bots) / signature (humains).
                UserTracker::info("[SESSION_{$type}] ⚠️ User {$user->wallet_address} (q=$q) cannot cover next bet ({$user->balance} < {$user->bet_amount}). Session ended.", ['wallet' => $user->wallet_address, 'q' => $q]);

                // P3 : ruine réelle -> 1 rattrapage, puis double échec -> file FIFO.
                $p3 = config('bankroll.enabled') && $user->bankroll_total !== null;
                if ($p3) {
                    $bs = app(\App\Services\BankrollSessionService::class);
                    if ((int) $user->session_attempt === 0 && (float) $user->bankroll_recovery_reserve > 0) {
                        $bs->startRecovery($user, $sessionBaseBet);
                        UserTracker::info("[P3] {$user->wallet_address} passe au rattrapage (R={$user->bankroll_recovery_reserve}).");
                        return; // la session reste ouverte : le joueur rejoue le rattrapage
                    }

                    // Rattrapage perdu = double échec -> dette distincte en file.
                    if (config('refund.enabled')) {
                        try {
                            $bs->enqueueRefund($user);
                        } catch (\Throwable $e) {
                            Log::warning('[REFUND] enqueue p3 failed for user ' . $user->id . ': ' . $e->getMessage());
                        }
                    }
                    $user->bankroll_total = null;
                    $user->session_attempt = 0;
                    $user->save();
                }

                // Modèle classique (non P3) : enqueue legacy désactivé par défaut
                // (évite de remplir la file avec des bots hors-P3 non finançables).
                if (! $p3 && config('refund.legacy_enqueue', false)) {
                    try {
                        $baseBet = (float) ($user->initial_base_bet ?? \App\Models\GameSetting::getValue('min_bet_eth', collect(config('pool.base_bet', [0.01]))->min()));
                        $security = (float) \App\Models\GameSetting::getValue('security_coefficient', config('game_settings.security_coefficient', 1000));
                        $capital = $baseBet * $security;
                        $bankroll = app(\App\Services\BankrollService::class)->recommendedBankroll($capital);
                        $refundPct = (float) config('refund.refund_pct', 0.25);
                        app(\App\Services\RefundQueueService::class)->enqueue($user->id, $refundPct * $bankroll, [
                            'refund_pct' => $refundPct,
                            'target_multiplier' => config('bankroll.session_target_q'),
                            'fee_rate' => config('bankroll.fee_rate'),
                            'type' => $type,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('[REFUND] enqueue failed for user ' . $user->id . ': ' . $e->getMessage());
                    }
                }

                $this->closeSession($user, 'stopped');

                if (! $sim) {
                    if (! $this->deferSideEffect('session_history', $user->id)) {
                        $this->historyService->archiveSessionHistory($user);
                    }

                    // DIVERGENCE: Bots get auto-payout for tests, Humans must manually withdraw
                    $payoutTriggered = false;
                    $signature = null;
                    $isBot = $this->isBotUser($user);

                if ($user->autoplay_active && $isBot) {
                    UserTracker::info("[BOT_AUTO_WITHDRAW] 🤖 Bot {$user->wallet_address} ruined. Forcing payout to clear balance for next run.", ['wallet' => $user->wallet_address]);
                    $this->deferPayout($user); // Forces sending whatever is left (outbox si activé)
                    $payoutTriggered = true;
                } else {
                    if ($user->balance <= 0.0001) {
                        UserTracker::info("[AUTO_EJECT] 🛑 Human player {$user->wallet_address} ruined with 0 ETH. Server pays gas to eject them from contract.", ['wallet' => $user->wallet_address]);
                        $this->deferPayout($user); // Forces payout from server (outbox si activé)
                        $payoutTriggered = true;
                    } else {
                        UserTracker::info("[MANUAL_WITHDRAWAL_REQUIRED] 🛑 Human player {$user->wallet_address} ruined. Funds ({$user->balance} ETH) kept in DB. Manual withdraw required.", ['wallet' => $user->wallet_address]);
                        $this->notificationService->notifyInsufficientBalance($user);
                        // Even on ruin, we provide the signature for the remaining funds
                        $sigData = $this->generateHumanSignature($user);
                        $signature = $sigData['signature'] ?? null;
                        $user->payout_signature = $signature;
                        $user->payout_deadline = $sigData['deadline'] ?? null;
                        $user->save();
                    }
                }

                    if (! $this->deferSideEffect('session_finished', $user->id, ['status' => $type, 'q' => (string) $q, 'payout_triggered' => $payoutTriggered, 'signature' => $signature])) {
                        \App\Helpers\BroadcastHelper::safe(fn() => event(new \App\Events\SessionFinished($user, $type, (string)$q, $payoutTriggered, $signature)), "SessionFinished user {$user->id} {$type}");
                    }
                }
            }

        } else {
            // CASE 3: Session Continues (RETURN TO POOL QUEUE)
            UserTracker::info("[SESSION_CONTINUE] 🔄 User {$user->wallet_address} continues session (q=$q < $multiplier). Current Balance: {$user->balance}.", ['wallet' => $user->wallet_address]);
            
            $user->status = 'available';
            $user->save();
        }

        // Note: syncLimitsToBlockchain() is now called before setNextSessionCooldown()
        // in the PAYOUT case (CASE 1) to ensure on-chain limits are fresh.
        // For CASE 2 (ruin) and CASE 3 (continue), sync limits here.
        if ($q < $multiplier && ! $sim) {
            if (! $this->deferSideEffect('sync_limits', $user->id)) {
                \App\Jobs\SyncUserLimitsJob::dispatch($user)->onQueue('limits');
            }
        }
    }

    /** Pousse un effet de bord dans l'outbox si activé. true = délégué (ne pas exécuter inline). */
    private function deferSideEffect(string $type, ?int $userId, array $payload = [], int $delay = 0): bool
    {
        if (! config('game_engine.defer_side_effects', false)) {
            return false;
        }
        try {
            app(\App\Services\SideEffectOutbox::class)->push($userId, $type, $payload, $delay);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[OUTBOX] push failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Déclenche le payout (immédiat, ou délégué à l'outbox). */
    private function deferPayout(User $user): void
    {
        if (! $this->deferSideEffect('payout', $user->id, [
            'wallet' => $user->wallet_address,
            'amount' => (float) $user->balance,
        ])) {
            $this->sendPayment($user);
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
                $q->whereIn('effect_type', ['base_bet_modifier', 'ceiling_increase', 'cooldown_reduction', 'q_special_unlock']);
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
        // Cooldown FIXE par palier de mise, réduit par les cartes actives.
        $minutes = $user->getBaseCooldownMinutes();

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
            $absEffect = abs($effectValue);
            if ($effectValue < 0) {
                // Valeur négative = minutes fixes à soustraire (ex: -1000 = -1000 min)
                $minutes = max(0, $minutes - $absEffect);
            } elseif ($effectValue < 1) {
                // Pourcentage (ex: 0.5 = -50%)
                $minutes = $minutes * (1 - $effectValue);
            } else {
                // Minutes fixes positives (ex: 60 = -60 min)
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
        // Cooldown FIXE par palier de mise.
        $minutes = $user->getBaseCooldownMinutes();

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
                $ev = $userCard->card ? $userCard->card->effect_value : 1;
                return ($ev > 0 && $ev < 1) ? 0 : 1;
            });

        foreach ($activeCooldownCards as $userCard) {
            $effectValue = $userCard->card->effect_value; // ex: 0.5 (50%), 60 (60 min), -1000 (-1000 min)
            $absEffect = abs($effectValue);
            if ($effectValue < 0) {
                // Valeur négative = minutes fixes à soustraire (ex: -1000 = -1000 min)
                $minutes = max(0, $minutes - $absEffect);
            } elseif ($effectValue < 1) {
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

    private function generateHumanSignature(User $user): ?array
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
                return [
                    'signature' => $response->json('signature'),
                    'deadline' => $response->json('deadline'),
                ];
            }

            Log::error("Bridge /generate-signature failed for {$user->wallet_address}: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to generate signature for {$user->wallet_address}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * RATTRAPAGE CLAIM (bug historique « bouton claim invisible »).
     *
     * À la fin de session, la signature de payout est générée via le bridge Node
     * (`POST /generate-signature`). Si la file de tx du bridge est engorgée par les
     * bots, l'appel peut expirer → `payout_signature` restait NULL alors que le
     * joueur a atteint son target → le front masquait la zone de CLAIM.
     *
     * Cette méthode garantit qu'un joueur HUMAIN en statut `stopped` avec un solde
     * > 0 possède une signature. Idempotente : ne fait rien si elle existe déjà.
     * Appelée par `/api/user/status` (rattrapage au polling).
     *
     * @return string|null la signature (existante ou régénérée), ou null
     */
    public function ensureHumanPayoutSignature(User $user): ?string
    {
        if (!empty($user->payout_signature)) {
            return $user->payout_signature;
        }
        // Uniquement en fin de session, avec des fonds, pour un humain.
        if ($user->status !== 'stopped' || $user->session_started) {
            return null;
        }
        if ((float) $user->balance <= 0.0) {
            return null;
        }
        if ($this->isBotUser($user)) {
            return null;
        }
        // Throttle : au plus un essai toutes les 10 s (évite de marteler le bridge).
        $cacheKey = "claim_sig_catchup:{$user->id}";
        if (!\Illuminate\Support\Facades\Cache::add($cacheKey, 1, 10)) {
            return null;
        }

        $sigData = $this->generateHumanSignature($user);
        if (empty($sigData['signature'])) {
            return null;
        }

        $user->payout_signature = $sigData['signature'];
        $user->payout_deadline = $sigData['deadline'] ?? null;
        $user->save();

        UserTracker::info("[CLAIM_CATCHUP] ✅ Signature de payout régénérée pour {$user->wallet_address} (solde {$user->balance}).", ['wallet' => $user->wallet_address]);

        return $user->payout_signature;
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
