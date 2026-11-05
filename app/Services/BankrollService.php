<?php

namespace App\Services;

/**
 * Calcul du bankroll unique et du facteur de récupération de session (P3).
 *
 * Modèle (voir config/bankroll.php) :
 *   t   = target_q − 1 (gain relatif par session)
 *   φ   = taux de frais, ajouté à la cible :  fee = φ·(1 + t)
 *   X   = (1 + t + fee) / t = (1 + t)·(1 + φ) / t
 *   B   = C + C·X            (dépôt, sans frais ajoutés)
 *
 * Les frais ne sont JAMAIS déduits du capital ni d'une session : ils sont
 * ajoutés à la cible (au montant à récupérer). Le gain du rattrapage
 * t·(C·X) couvre C + t·C + fee·C.
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
     * Frais relatifs au capital, ajoutés à la cible : fee = φ·(1 + t).
     */
    public function feeFraction(?float $targetQ = null): float
    {
        $t = ($targetQ ?? $this->targetQ()) - 1.0;

        return $this->feeRate() * (1.0 + $t);
    }

    /**
     * Facteur de récupération X = (1 + t + fee) / t.
     */
    public function recoveryFactor(?float $targetQ = null): float
    {
        $t = ($targetQ ?? $this->targetQ()) - 1.0;

        if ($t <= 0.0) {
            throw new \InvalidArgumentException('target_q doit être > 1.');
        }

        return (1.0 + $t + $this->feeFraction($targetQ)) / $t;
    }

    /**
     * Frais (en montant) ajoutés à la cible pour un capital de session donné.
     */
    public function feeInTarget(float $capital, ?float $targetQ = null): float
    {
        return $capital * $this->feeFraction($targetQ);
    }

    /**
     * Détail du bankroll pour un capital de session C.
     *
     * @return array{
     *   capital: float, target_q: float, target_gain: float, fee_rate: float,
     *   fee_fraction: float, fee_in_target: float, recovery_factor: float,
     *   recovery_capital: float, recovery_target_absolute: float, recovery_gain: float,
     *   fee_at_deposit: float, total: float, recovery_covers: float
     * }
     */
    public function breakdown(float $capital, ?float $targetQ = null): array
    {
        $targetQ = $targetQ ?? $this->targetQ();
        $t = $targetQ - 1.0;
        $x = $this->recoveryFactor($targetQ);
        $feeFraction = $this->feeFraction($targetQ);
        $feeInTarget = $capital * $feeFraction;
        $recoveryCapital = $capital * $x;

        return [
            'capital' => $capital,
            'target_q' => $targetQ,
            'target_gain' => $t,
            'fee_rate' => $this->feeRate(),
            'fee_fraction' => $feeFraction,
            'fee_in_target' => $feeInTarget,
            'recovery_factor' => $x,
            'recovery_capital' => $recoveryCapital,
            'recovery_target_absolute' => $recoveryCapital * (1.0 + $t),
            'recovery_gain' => $t * $recoveryCapital,
            // Aucun frais au dépôt : ils sont ajoutés à la cible.
            'fee_at_deposit' => 0.0,
            'total' => $capital + $recoveryCapital,
            // Vérification : gain du rattrapage == C + cible + frais.
            'recovery_covers' => $t * $recoveryCapital - ($capital * (1.0 + $t) + $feeInTarget),
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
