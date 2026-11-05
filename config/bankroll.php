<?php

/*
|--------------------------------------------------------------------------
| Modèle « bankroll + récupération de session » (P3)
|--------------------------------------------------------------------------
|
| Le joueur dépose UNE SEULE FOIS un bankroll total :
|
|     B = budget session 1 (C) + réserve de rattrapage (C × X) + frais
|
| avec X le facteur de récupération tel qu'une session de rattrapage GAGNANTE
| rembourse : le capital perdu (C) + la cible de la session 1 (t × C) + les frais.
|
| t = target_q − 1 (gain relatif visé par session, ex. 0.05 pour 1.05)
| φ = taux de frais prélevé UNE FOIS sur le bankroll total (ex. 0.005 = 0.5 %)
|
| Formule cohérente « frais sur le bankroll total » :
|
|     X = ((1 + t) − φ·t) / (t − φ·(1 + t))
|     B = C·(1 + X) / (1 − φ)
|     frais = φ·B
|
| (Si φ = 0 : X = (1 + t) / t = 1/t + 1, ce qui redonne la formule
|  X = (1 + T + fee) / T quand le terme de frais est nul.)
|
| La session de rattrapage utilise le MÊME target_q (même % de gain) mais sur un
| capital × X et une mise de base × X : sa cible en montant absolu est donc × X.
| Max 2 sessions (1 normale + 1 rattrapage). Si le rattrapage perd → perte finale.
|
*/

return [
    // Cible de session (multiplicateur). Gain relatif t = target_q − 1.
    'target_q' => (float) env('BANKROLL_TARGET_Q', 1.05),

    // Taux de frais prélevé une seule fois sur le bankroll total (fraction, pas %).
    'fee_rate' => (float) env('BANKROLL_FEE_RATE', 0.005),

    // Nombre total de sessions autorisées (1 + récupération).
    'max_sessions' => (int) env('BANKROLL_MAX_SESSIONS', 2),

    // Coefficient de sécurité : budget de session C = baseBet × coefficient.
    'security_coefficient' => (int) env('BANKROLL_SECURITY_COEFFICIENT', 1000),

    // Une session « perdue » = la totalité du capital de session est perdue (ruine).
    // (Le résidu éventuel, < mise de base, est négligé.)
    'loss_is_total' => (bool) env('BANKROLL_LOSS_IS_TOTAL', true),
];
