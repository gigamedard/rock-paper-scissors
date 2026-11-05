<?php

namespace App\Services;

/**
 * P3 — Bankroll + martingale de session (modèle A).
 *
 * C   = budget de session FIXE = baseBet × security_coefficient.
 * t   = session_target_q − 1 (0.05 pour 1.05, 0.20 pour 1.2).
 * X   = (1 + t) / t   (récupération, SANS frais : les sessions sont sans frais).
 * B   = bankroll crédité (le joueur paie en plus les frais au dépôt).
 *
 * Dépôt        : frais = fee_rate × B ; dépôt = B + frais (frais pris une fois).
 * Target bankroll : T = B × final_multiplier + frais.
 * Succès       : le joueur retire T ⇒ net = (final_multiplier − 1) × B.
 *
 * Session gagnante : +t·C au bankroll, C inchangé.
 * Session ruinée   : −C puis UNE récupération C·X (si B le permet) ;
 *   gagnée → +t·C·X, perdue → perte finale. Jamais X².
 */
class BankrollService
{
    public function sessionTargetQ(): float
    {
        return (float) config('bankroll.session_target_q', 1.05);
    }

    public function sessionGain(): float
    {
        return $this->sessionTargetQ() - 1.0;
    }

    public function finalMultiplier(): float
    {
        return (float) config('bankroll.final_multiplier', 2.0);
    }

    public function feeRate(): float
    {
        return (float) config('bankroll.fee_rate', 0.005);
    }

    public function securityCoefficient(): int
    {
        return (int) config('bankroll.security_coefficient', 1000);
    }

    /** Facteur de récupération X = (1 + t) / t (sans frais). */
    public function recoveryFactor(?float $sessionTargetQ = null): float
    {
        $t = ($sessionTargetQ ?? $this->sessionTargetQ()) - 1.0;

        if ($t <= 0.0) {
            throw new \InvalidArgumentException('session_target_q doit être > 1.');
        }

        return (1.0 + $t) / $t;
    }

    /** Gain d'une session normale gagnante : +t·C. */
    public function sessionWinAmount(float $capital): float
    {
        return $this->sessionGain() * $capital;
    }

    /** Capital d'une session de rattrapage : C·X. */
    public function recoveryCapital(float $capital): float
    {
        return $capital * $this->recoveryFactor();
    }

    /** Gain d'un rattrapage gagnant : t·C·X = C·(1+t). */
    public function recoveryWinAmount(float $capital): float
    {
        return $this->sessionGain() * $this->recoveryCapital($capital);
    }

    /** Bankroll recommandé : budget + une récupération. */
    public function recommendedBankroll(float $capital): float
    {
        return $capital + $this->recoveryCapital($capital);
    }

    /** Frais prélevés une seule fois au dépôt. */
    public function feeAmount(float $bankroll): float
    {
        return $this->feeRate() * $bankroll;
    }

    /** Montant total payé au dépôt (bankroll + frais). */
    public function depositAmount(float $bankroll): float
    {
        return $bankroll + $this->feeAmount($bankroll);
    }

    /** Target bankroll : T = B × final_multiplier + frais. */
    public function finalTarget(float $bankroll): float
    {
        return $bankroll * $this->finalMultiplier() + $this->feeAmount($bankroll);
    }

    /** Montant retiré en cas de succès (= T). */
    public function payoutOnSuccess(float $bankroll): float
    {
        return $this->finalTarget($bankroll);
    }

    /** Net pour le joueur en cas de succès : (mult − 1)·B (ex. B si ×2). */
    public function playerNet(float $bankroll): float
    {
        return $this->payoutOnSuccess($bankroll) - $this->depositAmount($bankroll);
    }

    public function isSuccess(float $bankroll, float $initialBankroll): bool
    {
        return $bankroll >= $this->finalTarget($initialBankroll) - 1e-9;
    }

    public function canFundRecovery(float $bankroll, float $capital): bool
    {
        return $bankroll >= $this->recoveryCapital($capital) - 1e-9;
    }

    public function isRuin(float $bankroll, float $capital): bool
    {
        return $bankroll < $capital - 1e-9;
    }

    /**
     * @return array<string, float|bool>
     */
    public function breakdown(float $capital, ?float $initialBankroll = null): array
    {
        $t = $this->sessionGain();
        $x = $this->recoveryFactor();
        $bankroll = $initialBankroll ?? $this->recommendedBankroll($capital);
        $fee = $this->feeAmount($bankroll);

        return [
            'capital' => $capital,
            'session_target_q' => $this->sessionTargetQ(),
            'session_gain' => $t,
            'recovery_factor' => $x,
            'recovery_capital' => $capital * $x,
            'recovery_win' => $t * $capital * $x,
            'bankroll' => $bankroll,
            'fee_rate' => $this->feeRate(),
            'fee_once' => $fee,
            'deposit_total' => $bankroll + $fee,
            'final_multiplier' => $this->finalMultiplier(),
            'final_target' => $this->finalTarget($bankroll),
            'payout_on_success' => $this->payoutOnSuccess($bankroll),
            'player_net_on_success' => $this->playerNet($bankroll),
            'recommended_min_bankroll' => $this->recommendedBankroll($capital),
        ];
    }
}
