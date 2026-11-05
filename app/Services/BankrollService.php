<?php

namespace App\Services;

/**
 * P3 — Bankroll + martingale de session (modèle « A »).
 *
 * C   = budget de session FIXE = baseBet × security_coefficient (marge de sécurité).
 * t   = session_target_q − 1  (ex. 0.05 pour 1.05, 0.20 pour 1.2)
 * fee = taux de frais (ex. 0.005), prélevé UNE SEULE FOIS au début.
 * X   = (1 + t + fee) / t   (facteur de récupération ; fee = 0 → (1+t)/t)
 * B   = bankroll du joueur. Une ruine puise C·X dans B pour UNE récupération.
 * Objectif final : B ≥ 2 × B_init (doubler le bankroll).
 *
 * Session gagnante : +t·C au bankroll, C inchangé, nouvelle session.
 * Session ruinée    : −C puis, si B ≥ C·X, une session de rattrapage (C·X).
 *   - rattrapage gagné : +t·C·X, on continue ;
 *   - rattrapage perdu : perte finale.
 * À chaque ruine : une seule récupération (jamais X², X³…).
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

    /** Facteur de récupération X = (1 + t + fee) / t. */
    public function recoveryFactor(?float $sessionTargetQ = null, ?float $feeRate = null): float
    {
        $t = ($sessionTargetQ ?? $this->sessionTargetQ()) - 1.0;
        $fee = $feeRate ?? $this->feeRate();

        if ($t <= 0.0) {
            throw new \InvalidArgumentException('session_target_q doit être > 1.');
        }

        return (1.0 + $t + $fee) / $t;
    }

    /** Frais prélevés une seule fois au début. */
    public function feeAmount(float $capital): float
    {
        return $this->feeRate() * $capital;
    }

    /** Gain d'une session normale gagnante. */
    public function sessionWinAmount(float $capital): float
    {
        return $this->sessionGain() * $capital;
    }

    /** Capital d'une session de rattrapage. */
    public function recoveryCapital(float $capital): float
    {
        return $capital * $this->recoveryFactor();
    }

    /** Gain d'un rattrapage gagnant (couvre capital perdu + cible + frais). */
    public function recoveryWinAmount(float $capital): float
    {
        return $this->sessionGain() * $this->recoveryCapital($capital);
    }

    /** Bankroll recommandé : budget + une récupération. */
    public function recommendedBankroll(float $capital): float
    {
        return $capital + $this->recoveryCapital($capital);
    }

    /** Cible finale (doubler le bankroll initial). */
    public function finalTarget(float $initialBankroll): float
    {
        return $this->finalMultiplier() * $initialBankroll;
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
     * Résumé des paramètres/étapes pour un budget C et (optionnel) un bankroll initial.
     *
     * @return array<string, float|bool>
     */
    public function breakdown(float $capital, ?float $initialBankroll = null): array
    {
        $t = $this->sessionGain();
        $x = $this->recoveryFactor();
        $initial = $initialBankroll ?? $this->recommendedBankroll($capital);

        return [
            'capital' => $capital,
            'session_target_q' => $this->sessionTargetQ(),
            'session_gain' => $t,
            'fee_rate' => $this->feeRate(),
            'fee_once' => $this->feeAmount($capital),
            'recovery_factor' => $x,
            'recovery_capital' => $capital * $x,
            'recovery_win' => $t * $capital * $x,
            'bankroll_initial' => $initial,
            'final_multiplier' => $this->finalMultiplier(),
            'final_target' => $this->finalTarget($initial),
            'recommended_min_bankroll' => $this->recommendedBankroll($capital),
        ];
    }
}
