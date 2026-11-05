<?php

namespace App\Services;

/**
 * Modèle « bankroll + sessions » (P3) — voir config/bankroll.php.
 *
 * DEUX targets distincts :
 *   - session_target_q : gain par session (1.05, 1.2…), appliqué au capital C.
 *   - bankroll_target  : objectif final du bankroll (1.5, 2, 2.5…).
 *
 * Dépôt unique : B = C + C·X, avec X = (1 + t_s) / t_s, t_s = session_target_q − 1.
 * Cible bankroll : T = B × (bankroll_target + φ), frais = φ·B ajoutés à la cible.
 * Aucun frais de session. Capital de session FIXE = C.
 */
class BankrollService
{
    public function sessionTargetQ(): float
    {
        return (float) config('bankroll.session_target_q', 1.05);
    }

    /** Gain relatif d'une session (t_s = session_target_q − 1). */
    public function sessionGain(): float
    {
        return $this->sessionTargetQ() - 1.0;
    }

    public function bankrollTarget(): float
    {
        return (float) config('bankroll.bankroll_target', 2.0);
    }

    public function feeRate(): float
    {
        return (float) config('bankroll.fee_rate', 0.005);
    }

    public function securityCoefficient(): int
    {
        return (int) config('bankroll.security_coefficient', 1000);
    }

    /**
     * Multiplicateur de réserve X = (1 + t_s) / t_s.
     */
    public function reserveMultiplier(?float $sessionTargetQ = null): float
    {
        $t = ($sessionTargetQ ?? $this->sessionTargetQ()) - 1.0;

        if ($t <= 0.0) {
            throw new \InvalidArgumentException('session_target_q doit être > 1.');
        }

        return (1.0 + $t) / $t;
    }

    /**
     * Détail du bankroll pour un capital de session C.
     *
     * @return array{
     *   capital: float, session_target_q: float, session_gain: float,
     *   reserve_multiplier: float, reserve: float, bankroll: float,
     *   bankroll_target: float, fee_rate: float, fee: float,
     *   target_amount: float, payout_net: float, success_probability: float
     * }
     */
    public function breakdown(float $capital): array
    {
        $t = $this->sessionGain();
        $x = $this->reserveMultiplier();
        $reserve = $capital * $x;
        $bankroll = $capital + $reserve;
        $fee = $this->feeRate() * $bankroll;
        $target = $bankroll * ($this->bankrollTarget() + $this->feeRate());

        // Ruine quand bankroll < C ; succès quand bankroll ≥ T.
        $successProbability = ($bankroll - $capital) > 0.0
            ? max(0.0, min(1.0, ($bankroll - $capital) / ($target - $capital)))
            : 0.0;

        return [
            'capital' => $capital,
            'session_target_q' => $this->sessionTargetQ(),
            'session_gain' => $t,
            'reserve_multiplier' => $x,
            'reserve' => $reserve,
            'bankroll' => $bankroll,
            'bankroll_target' => $this->bankrollTarget(),
            'fee_rate' => $this->feeRate(),
            'fee' => $fee,
            // Montant à atteindre (inclut les frais).
            'target_amount' => $target,
            // Net encaissé par le joueur quand T est atteint.
            'payout_net' => $bankroll * $this->bankrollTarget(),
            'success_probability' => $successProbability,
        ];
    }

    /** Capital de session (fixe). */
    public function sessionCapital(float $capital): float
    {
        return $capital;
    }

    /** Gain d'une session gagnante (t·C). */
    public function sessionWinAmount(float $capital): float
    {
        return $this->sessionGain() * $capital;
    }

    /** Succès : le bankroll a atteint la cible. */
    public function reachedTarget(float $bankroll, float $initialBankroll): bool
    {
        $target = $initialBankroll * ($this->bankrollTarget() + $this->feeRate());

        return $bankroll >= $target - 1e-9;
    }

    /** Ruine : plus assez de capital pour financer une session. */
    public function isRuin(float $bankroll, float $capital): bool
    {
        return $bankroll < $capital - 1e-9;
    }
}
