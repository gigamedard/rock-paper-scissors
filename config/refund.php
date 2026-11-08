<?php

/*
|--------------------------------------------------------------------------
| Fonds de recouvrement + file FIFO (P3)
|--------------------------------------------------------------------------
|
| Le fonds est alimenté par les frais. Les joueurs qui perdent les 2 sessions
| sont mis en file (FIFO). Un batch paie les premiers de la file dès que le
| fonds atteint `trigger_pct` de la dette totale, en payant au plus
| `batch_pct` de la file. Le solde ne devient jamais négatif.
|
| Règle de sûreté : le taux de remboursement doit rester <= la couverture
| (frais encaissés / remboursements attendus) pour que la file se vide.
|
*/

return [
    // Seuil de déclenchement : le fonds doit couvrir X % de la dette totale.
    'trigger_pct' => (float) env('REFUND_TRIGGER_PCT', 0.10),

    // Fraction de la file payée à chaque batch.
    'batch_pct' => (float) env('REFUND_BATCH_PCT', 0.05),

    // Marge de sûreté appliquée au taux de remboursement proposé.
    'safety_margin' => (float) env('REFUND_SAFETY_MARGIN', 0.90),
];
