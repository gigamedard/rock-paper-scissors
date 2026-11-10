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

    /** Paramètres depuis la config. */
    public function params(float $baseBet): array
    {
        return self::computeParams(
            $baseBet,
            (float) config('bankroll.session_target_q', 1.05),
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
    public function initialize(\App\Models\User $user, float $baseBet): array
    {
        $p = $this->params($baseBet);

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

        return $p;
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
        $user->bet_amount = $baseBet;
        $user->status = 'available';
        $user->pool_id = null;
        if ($user->preMove) {
            $user->preMove->current_index = 0;
            $user->preMove->save();
        }
        $user->save();
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
