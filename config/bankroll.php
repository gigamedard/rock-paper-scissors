<?php

/*
|--------------------------------------------------------------------------
| Modèle « bankroll + récupération de session » (P3)
|--------------------------------------------------------------------------
|
| Le joueur dépose UNE SEULE FOIS un bankroll :
|
|     B = C + C·X      (budget session 1 + réserve de rattrapage)
|
| Aucun frais n'est ajouté au dépôt, ni déduit du capital, ni prélevé par
| session. Les frais sont AJOUTÉS À LA CIBLE (au montant à récupérer).
|
| t   = target_q − 1  (gain relatif visé par session, ex. 0.05 pour 1.05)
| φ   = taux de frais (ex. 0.005 = 0.5 %), appliqué à (1 + t) → frais relatifs
|       au capital : fee = φ·(1 + t)
|
| Une session de rattrapage gagnante doit rembourser :
|
|     capital perdu (C) + cible session 1 (t·C) + frais (fee·C)
|
| avec un gain t·(C·X), d'où :
|
|     X = (1 + t + fee) / t = (1 + t)·(1 + φ) / t
|
| La session de rattrapage vise le MÊME pourcentage t, sur un capital C×X et
| une mise de base × X (cible absolue × X). Max 2 sessions (1 + 1 rattrapage).
| Si le rattrapage perd → perte finale.
|
*/

return [
    // Cible de session (multiplicateur). Gain relatif t = target_q − 1.
    'target_q' => (float) env('BANKROLL_TARGET_Q', 1.05),

    // Taux de frais ajouté à la cible (fraction, pas %). Jamais déduit du capital.
    'fee_rate' => (float) env('BANKROLL_FEE_RATE', 0.005),

    // Nombre total de sessions autorisées (1 + récupération).
    'max_sessions' => (int) env('BANKROLL_MAX_SESSIONS', 2),

    // Coefficient de sécurité : budget de session C = baseBet × coefficient.
    'security_coefficient' => (int) env('BANKROLL_SECURITY_COEFFICIENT', 1000),

    // Une session « perdue » = la totalité du capital de session est perdue (ruine).
    'loss_is_total' => (bool) env('BANKROLL_LOSS_IS_TOTAL', true),
];
