<?php

namespace App\Services;

/**
 * Calcul du bankroll unique et du facteur de récupération de session (P3).
 *
 * Modèle (voir config/bankroll.php) :
 *   t = target_q − 1 (gain relatif par session)
 *   φ = taux de frais prélevé une fois sur le bankroll total
 *
 *   X = ((1 + t) − φ·t) / (t − φ·(1 + t))
 *   B = C·(1 + X) / (1 − φ)
 *
 * La session de rattrapage vise le MÊME pourcentage t, sur un capital C·X
 * (donc une cible absolue × X) : son gain t·C·X couvre C + t·C + frais.
 */
class BankrollService
{
    public function targetQ(): float
    {
        return (float) config('bankroll.target_q', 1.05);
    }

    public function feeRate(): float
    {
        return (float) config('bankroll.fee_rate', 0.005);
    }

    public function securityCoefficient(): int
    {
        return (int) config('bankroll.security_coefficient', 1000);
    }

    public function maxSessions(): int
    {
        return (int) config('bankroll.max_sessions', 2);
    }

    /** Gain relatif visé par session (t = target_q − 1). */
    public function targetGain(): float
    {
        return $this->targetQ() - 1.0;
    }

    /**
     * Facteur de récupération X.
     */
    public function recoveryFactor(?float $targetQ = null, ?float $feeRate = null): float
    {
        $t = ($targetQ ?? $this->targetQ()) - 1.0;
        $phi = $feeRate ?? $this->feeRate();

        if ($t <= 0.0) {
            throw new \InvalidArgumentException('target_q doit être > 1.');
        }

        $num = (1.0 + $t) - $phi * $t;
        $den = $t - $phi * (1.0 + $t);

        if ($den <= 0.0) {
            throw new \InvalidArgumentException('Combinaison target_q/frais invalide (dénominateur <= 0).');
        }

        return $num / $den;
    }

    /**
     * Détail du bankroll pour un capital de session C.
     *
     * @return array{
     *   capital: float, target_q: float, target_gain: float, fee_rate: float,
     *   recovery_factor: float, recovery_capital: float, recovery_target_absolute: float,
     *   recovery_gain: float, fee: float, total: float
     * }
     */
    public function breakdown(float $capital, ?float $targetQ = null, ?float $feeRate = null): array
    {
        $targetQ = $targetQ ?? $this->targetQ();
        $phi = $feeRate ?? $this->feeRate();
        $t = $targetQ - 1.0;
        $x = $this->recoveryFactor($targetQ, $phi);

        $recoveryCapital = $capital * $x;
        $total = $capital * (1.0 + $x) / (1.0 - $phi);
        $fee = $total * $phi;

        return [
            'capital' => $capital,
            'target_q' => $targetQ,
            'target_gain' => $t,
            'fee_rate' => $phi,
            'recovery_factor' => $x,
            'recovery_capital' => $recoveryCapital,
            'recovery_target_absolute' => $recoveryCapital * (1.0 + $t),
            'recovery_gain' => $t * $recoveryCapital,
            'fee' => $fee,
            'total' => $total,
            // Vérification : le gain de la récupération couvre C + cible + frais.
            'recovery_covers' => $t * $recoveryCapital - ($capital * (1.0 + $t) + $fee),
        ];
    }

    /**
     * Capital de session courant selon la tentative (0 = normale, 1 = rattrapage).
     */
    public function sessionCapital(float $baseCapital, int $attempt, ?float $recoveryFactor = null): float
    {
        $x = $recoveryFactor ?? $this->recoveryFactor();

        return $attempt <= 0 ? $baseCapital : $baseCapital * $x;
    }
}
