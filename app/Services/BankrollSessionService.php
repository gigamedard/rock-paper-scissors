<?php

namespace App\Services;

/**
 * State machine P3 — bankroll + sessions (modèle b).
 *
 *   C  = baseBet × security_coefficient (capital session 1)
 *   T  = session target (multiplicateur)
 *   φ  = fee rate ; φ_used = φ·T
 *   t  = T − 1
 *   X  = (1 + t + φ_used) / t
 *   R  = C·X (réserve de rattrapage)
 *   B  = C + R (bankroll total)
 *
 * Détection de succès via le mécanisme existant : target_q = T et
 * fee_percent = φ×100 donnent multiplier = T(1+φ) = T + φ_used.
 *   - session 1 : solde initial = C  → seuil = C·T·(1+φ)
 *   - rattrapage : solde initial = R → seuil = R·T·(1+φ)
 *
 * Transitions (on considère le solde de jeu de la session courante) :
 *   solde ≥ seuil        → win
 *   solde < mise de base → attempt 0 : recovery ; attempt 1 : double_failure
 *   sinon                → continue
 */
class BankrollSessionService
{
    /**
     * Paramètres purs (aucune dépendance framework) — testable.
     */
    public static function computeParams(
        float $baseBet,
        float $targetMultiplier,
        float $feeRate,
        int $securityCoefficient,
        float $refundPct
    ): array {
        $C = $baseBet * $securityCoefficient;
        $T = $targetMultiplier;
        $t = $T - 1.0;

        if ($t <= 0.0) {
            throw new \InvalidArgumentException('target_multiplier doit être > 1.');
        }

        $phiUsed = $feeRate * $T;
        $X = (1.0 + $t + $phiUsed) / $t;
        $R = $C * $X;
        $B = $C + $R;

        return [
            'C' => $C,
            'T' => $T,
            't' => $t,
            'phi' => $feeRate,
            'phi_used' => $phiUsed,
            'X' => $X,
            'R' => $R,
            'B' => $B,
            'F_C' => $phiUsed * $C,
            'F_R' => $phiUsed * $R,
            'refund_pct' => $refundPct,
            'refund_amount' => $refundPct * $B,
            'target_q' => $T,
            'fee_percent' => $feeRate * 100.0,
            'threshold_1' => $C * $T * (1.0 + $feeRate),
            'threshold_2' => $R * $T * (1.0 + $feeRate),
            // Couverture du fonds (frais / remboursements attendus) pour info.
            'coverage' => null,
        ];
    }

    /**
     * Paramètres depuis la config, avec cible de session optionnelle.
     * En P3 la cible choisie par le joueur (1.05 / 1.2 / ...) pilote X et donc
     * la réserve R et le dépôt B = C + R.
     */
    public function params(float $baseBet, ?float $targetMultiplier = null): array
    {
        $target = $targetMultiplier !== null && $targetMultiplier > 1.0
            ? $targetMultiplier
            : (float) config('bankroll.session_target_q', 1.05);

        return self::computeParams(
            $baseBet,
            $target,
            (float) config('bankroll.fee_rate', 0.005),
            (int) config('bankroll.security_coefficient', 1000),
            (float) config('refund.refund_pct', 0.25)
        );
    }

    /** Facteur de récupération X = (1+t+φ_used)/t (avec frais). */
    public static function recoveryFactor(float $targetMultiplier, float $feeRate): float
    {
        $t = $targetMultiplier - 1.0;
        if ($t <= 0.0) {
            return 1.0;
        }

        return (1.0 + $t + $feeRate * $targetMultiplier) / $t;
    }

    /** Activation gardée : le joueur doit avoir déposé au moins B = C + R. */
    public static function canActivate(float $onChainBalance, array $params): bool
    {
        return $onChainBalance + 1e-9 >= $params['B'];
    }

    /**
     * Transition pure (testable).
     *
     * @param  array{attempt:int,capital:float,T:float,phi:float}  $state
     * @return string 'win'|'recovery'|'double_failure'|'continue'
     */
    public static function nextAction(array $state, float $balance, float $bet): string
    {
        $threshold = $state['capital'] * $state['T'] * (1.0 + $state['phi']);
        if ($balance + 1e-9 >= $threshold) {
            return 'win';
        }
        if ($balance < $bet - 1e-9) {
            return ((int) $state['attempt']) <= 0 ? 'recovery' : 'double_failure';
        }

        return 'continue';
    }

    /**
     * Initialise le bankroll d'un joueur et "découpe" le dépôt :
     *   reserve R -> bankroll_remaining ; balance de jeu -> C.
     */
    public function initialize(\App\Models\User $user, float $baseBet, ?float $targetMultiplier = null): array
    {
        $p = $this->params($baseBet, $targetMultiplier);

        $user->bankroll_total = $p['B'];
        $user->bankroll_remaining = $p['R'];
        $user->bankroll_fee = $p['F_C'] + $p['F_R'];
        $user->bankroll_recovery_reserve = $p['R'];
        $user->recovery_factor = $p['X'];
        $user->session_capital = $p['C'];
        $user->session_attempt = 0;
        $user->recovery_used = false;
        $user->bankroll_target = $p['T'];
        $user->bankroll_target_amount = $p['B'] * $p['T'];
        $user->sessions_played = 0;
        $user->target_q = $p['target_q'];
        $user->balance = $p['C'];
        $user->battle_balance = 0;
        $user->session_start_balance = $p['C'];
        $user->save();

        // Fonds de recouvrement : frais de session 1 (F_C) = φ_used·C.
        try {
            app(RefundQueueService::class)->credit($p['F_C'], 'session_fee_C', 'user:' . $user->id);
        } catch (\Throwable $e) {
            // non bloquant
        }

        return $p;
    }

    /**
     * Réinitialise une session P3 à partir du total crédité on-chain (top-up).
     *
     * Le total est cumulatif (dépôts − payouts). On garde
     *   C = baseBet × security_coefficient
     *   R = total − C   (réserve réelle, éventuellement > C·X recommandé)
     *   X = R / C       (même ratio capital/mise ⇒ même durée de rattrapage)
     * et on repart sur une session 1 propre.
     *
     * @return array{params:array,reserve:float,factor:float,total:float}|null
     */
    public function reinitializeFromTotal(\App\Models\User $user, float $total, ?float $targetMultiplier = null): ?array
    {
        $baseBet = (float) ($user->initial_base_bet ?: $user->bet_amount ?: 0);
        if ($baseBet <= 0) {
            $baseBet = (float) collect(config('pool.base_bet', [0.0004]))->min();
        }

        $target = $targetMultiplier !== null && $targetMultiplier > 1.0
            ? $targetMultiplier
            : (float) ($user->target_q ?: config('bankroll.session_target_q', 1.05));

        $p = $this->params($baseBet, $target);
        $C = (float) $p['C'];

        // Pas assez pour financer une session P3 : on laisse le solde brut.
        if ($total + 1e-9 < $C) {
            $user->pending_bankroll_total = null;
            $user->bankroll_total = null;
            $user->bankroll_remaining = null;
            $user->bankroll_recovery_reserve = null;
            $user->recovery_factor = null;
            $user->session_capital = null;
            $user->session_attempt = 0;
            $user->recovery_used = false;
            $user->balance = $total;
            $user->battle_balance = 0;
            $user->session_start_balance = $total;
            $user->session_start_battle_balance = 0;
            $user->session_started = false;
            $user->status = 'available';
            $user->bet_amount = $baseBet;
            $user->save();

            return null;
        }

        $reserve = $total - $C;
        $factor = $C > 0 ? $reserve / $C : 1.0;

        $user->pending_bankroll_total = null;
        $user->bankroll_total = $total;
        $user->bankroll_remaining = $reserve;
        $user->bankroll_recovery_reserve = $reserve;
        $user->recovery_factor = $factor;
        $user->session_capital = $C;
        $user->session_attempt = 0;
        $user->recovery_used = false;
        $user->bankroll_target = $p['T'];
        $user->bankroll_target_amount = $total * $p['T'];
        $user->target_q = $p['target_q'];
        $user->balance = $C;
        $user->battle_balance = 0;
        $user->session_start_balance = $C;
        $user->session_start_battle_balance = 0;
        $user->session_started = false;
        $user->status = 'available';
        $user->bet_amount = $baseBet;
        $user->save();

        return ['params' => $p, 'reserve' => $reserve, 'factor' => $factor, 'total' => $total];
    }

    /**
     * Applique un top-up en attente si le joueur n'est plus dans un pool.
     * À appeler en fin de pool et avant le matchmaking.
     */
    public function applyPending(\App\Models\User $user): bool
    {
        if ($user->pending_bankroll_total === null) {
            return false;
        }
        if ((float) $user->battle_balance > 0 || $user->pool_id !== null) {
            return false;
        }
        if ($user->payout_signature !== null) {
            return false;
        }
        if ($user->cooldown_until && $user->cooldown_until->isFuture()) {
            return false;
        }

        $target = (float) ($user->target_q ?? 0);
        $this->reinitializeFromTotal($user, (float) $user->pending_bankroll_total, $target > 1.0 ? $target : null);

        return true;
    }

    /** Démarre la session de rattrapage : libère R comme solde de jeu. */
    public function startRecovery(\App\Models\User $user, float $baseBet): void
    {
        $R = (float) $user->bankroll_recovery_reserve;

        $user->balance = $R;
        $user->battle_balance = 0;
        $user->session_capital = $R;
        $user->session_attempt = 1;
        $user->recovery_used = true;
        $user->session_start_balance = $R;
        // La mise est multipliée par X (la cible reste le même % de capital)
        // -> la session de rattrapage dure ~le même temps qu'une session normale.
        $user->bet_amount = $baseBet * (float) ($user->recovery_factor ?: 1.0);
        $user->status = 'available';
        $user->pool_id = null;
        if ($user->preMove) {
            $user->preMove->current_index = 0;
            $user->preMove->save();
        }
        $user->save();

        // Fonds de recouvrement : frais de rattrapage (F_R) = φ_used·R.
        try {
            $phiUsed = (float) config('bankroll.fee_rate', 0.005)
                * (float) ($user->bankroll_target ?: config('bankroll.session_target_q', 1.05));
            app(RefundQueueService::class)->credit($phiUsed * $R, 'recovery_fee_R', 'user:' . $user->id);
        } catch (\Throwable $e) {
            // non bloquant
        }
    }

    /** Double échec : enfile un remboursement distinct (dette indépendante). */
    public function enqueueRefund(\App\Models\User $user): void
    {
        $refundPct = (float) config('refund.refund_pct', 0.25);
        $amount = $refundPct * (float) $user->bankroll_total;

        app(RefundQueueService::class)->enqueue($user->id, $amount, [
            'refund_pct' => $refundPct,
            'target_multiplier' => $user->bankroll_target,
            'fee_rate' => (float) config('bankroll.fee_rate', 0.005),
        ]);
    }
}
