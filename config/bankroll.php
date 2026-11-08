<?php

/*
|--------------------------------------------------------------------------
| P3 — Bankroll + martingale de session (modèle A)
|--------------------------------------------------------------------------
|
| • Budget de session C = baseBet × security_coefficient (FIXE).
| • Target de SESSION tq_s (1.05 / 1.2) : une session joue C vers C·(1+t),
|   t = tq_s − 1.  **Les sessions sont calculées SANS frais.**
| • Facteur de récupération X = (1 + t) / t  (sans frais).
| • Dépôt : le joueur paie B + frais ; les frais sont prélevés à ce moment,
|   seul B est crédité au bankroll.  frais = fee_rate × B.
| • Target de BANKROLL : T = (B × final_multiplier) + frais.
|   En cas de succès le joueur retire T ⇒ net = (final_multiplier − 1) × B
|   (ex. multiplier 2 → il a doublé).
| • Objectif final : atteindre T (doubler le bankroll, frais inclus).
|
| Déroulé :
|   - Session gagnante : +t·C au bankroll ; C inchangé ; nouvelle session.
|   - Session ruinée   : −C, puis UNE récupération C·X puisée dans B
|     (gagnée → +t·C·X ; perdue → perte finale).
|   - À chaque ruine : une seule récupération (jamais X², X³…).
*/

return [
    // Active la state machine bankroll (sessions C/R + récupération) en live.
    // Nécessite un dépôt B = C+R (activation gardée par canActivate()).
    'enabled' => (bool) env('BANKROLL_ENABLED', false),

    // Target de session (1.05 ou 1.2).
    'session_target_q' => (float) env('BANKROLL_SESSION_TARGET_Q', 1.05),

    // Multiplicateur final du bankroll (2.0 = doubler).
    'final_multiplier' => (float) env('BANKROLL_FINAL_MULTIPLIER', 2.0),

    // Taux de frais prélevé UNE FOIS au dépôt, et ajouté à la target bankroll.
    'fee_rate' => (float) env('BANKROLL_FEE_RATE', 0.005),

    // Coefficient de sécurité : C = baseBet × coefficient.
    'security_coefficient' => (int) env('BANKROLL_SECURITY_COEFFICIENT', 1000),

    // Une récupération autorisée par ruine.
    'recovery_per_ruin' => true,
];
