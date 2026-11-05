<?php

/*
|--------------------------------------------------------------------------
| P3 — Bankroll + martingale de session (modèle validé « A »)
|--------------------------------------------------------------------------
|
| • Budget de session C = baseBet × security_coefficient (FIXE pour toutes
|   les sessions). C'est la marge de sécurité.
| • Target de SESSION T_s (1.05 ou 1.2) : une session joue C vers C·(1+t),
|   t = T_s − 1.
| • Frais prélevés UNE SEULE FOIS au début de la première session.
| • Facteur de récupération X = (1 + t + fee) / t.
|   (ex. target 1.05 + frais 0.5 % → X = 21.10 ; target 1.2 → X = 6.03)
| • Bankroll B = argent total du joueur. B_init est le dépôt.
|   Recommandation : B_init ≥ C + C·X (budget + une récupération).
| • Objectif FINAL : doubler le bankroll (B ≥ 2 × B_init).
|
| Déroulé :
|   - Session gagnante  : profit t·C versé au bankroll ; C inchangé ; nouvelle session.
|   - Session ruinée    : C perdu ; on puise C·X dans le bankroll pour UNE
|                          session de rattrapage (même t). Si le rattrapage
|                          gagne → on continue ; s'il perd → perte finale.
|   - À CHAQUE ruine : une seule récupération (jamais X², X³…).
|   - Arrêt : succès si B ≥ 2·B_init ; perte finale si le rattrapage perd.
|
*/

return [
    // Target de session (1.05 ou 1.2).
    'session_target_q' => (float) env('BANKROLL_SESSION_TARGET_Q', 1.05),

    // Multiplicateur final du bankroll (2.0 = doubler).
    'final_multiplier' => (float) env('BANKROLL_FINAL_MULTIPLIER', 2.0),

    // Taux de frais, prélevé UNE FOIS au début (fraction, pas %).
    'fee_rate' => (float) env('BANKROLL_FEE_RATE', 0.005),

    // Coefficient de sécurité : C = baseBet × coefficient.
    'security_coefficient' => (int) env('BANKROLL_SECURITY_COEFFICIENT', 1000),

    // Une récupération autorisée par ruine (jamais de récupération de récupération).
    'recovery_per_ruin' => true,
];
