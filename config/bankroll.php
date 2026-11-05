<?php

/*
|--------------------------------------------------------------------------
| Modèle « bankroll + sessions » (P3)
|--------------------------------------------------------------------------
|
| DEUX targets distincts :
|
|   • target de SESSION (tq_s, ex. 1.05) : chaque session vise +(tq_s − 1)
|     de SON capital de session. Aucun frais de session.
|
|   • target de BANKROLL (tq_b, ex. 1.5 / 2 / 2.5) : objectif final du
|     bankroll (ex. 2 = doubler). Montant cible :
|
|         T = B × (tq_b + φ)        avec φ = fee_rate
|
|     dont frais = φ × B (appliqués au bankroll, ajoutés à la cible).
|     À T atteint : le joueur encaisse B × tq_b (net), la plateforme garde φ×B.
|
| Dépôt UNIQUE : B = C + C·X, sans frais ajoutés au dépôt, avec
|
|     t = tq_s − 1,  X = (1 + t) / t = 1/t + 1
|
| Le capital d'une session est FIXE : C = baseBet × security_coefficient,
| prélevé du bankroll à chaque session. Gain d'une session gagnante : t·C
| ajouté au bankroll ; perte : −C. On relance tant que bankroll ≥ C et < T.
|
| Success ≈ (B − C) / (T − C) ; ruin quand bankroll < C.
|
*/

return [
    // Target de SESSION (gain par session). t = tq_s − 1.
    'session_target_q' => (float) env('BANKROLL_SESSION_TARGET_Q', 1.05),

    // Target de BANKROLL (objectif final : 1.5, 2, 2.5…).
    'bankroll_target' => (float) env('BANKROLL_TARGET', 2.0),

    // Frais (fraction) appliqués au bankroll et ajoutés à la cible.
    'fee_rate' => (float) env('BANKROLL_FEE_RATE', 0.005),

    // Coefficient de sécurité : C = baseBet × coefficient.
    'security_coefficient' => (int) env('BANKROLL_SECURITY_COEFFICIENT', 1000),
];
