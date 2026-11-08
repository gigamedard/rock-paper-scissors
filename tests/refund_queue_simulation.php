<?php
/**
 * Test/validation de l'algorithme du fonds de recouvrement + file FIFO.
 * Exécution : php tests/refund_queue_simulation.php
 * (ne touche pas la base : teste la logique pure + simule des cohortes)
 */

require __DIR__ . '/../app/Services/RefundQueueService.php';

use App\Services\RefundQueueService;

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ✅ {$label}" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "  ❌ {$label}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

echo "=== Tests unitaires de planPayouts ===\n";

$r = RefundQueueService::planPayouts(0.0, []);
check('file vide → aucun paiement', $r['payouts'] === [] && $r['paid'] == 0.0);

// dette totale = 100 -> seuil = 10 % × 100 = 10 ; fonds 0.5 < 10 -> non déclenché
$r = RefundQueueService::planPayouts(0.5, [['id' => 1, 'amount' => 100.0]]);
check('fonds sous le seuil (0.5 < 10 % × 100) → aucun paiement', $r['payouts'] === [] && !$r['triggered']);

// file de 100, montant 1, fonds 10 -> trigger 10 % de 100 = 10, batch 5 % = 5
$queue = [];
for ($i = 1; $i <= 100; $i++) {
    $queue[] = ['id' => $i, 'amount' => 1.0];
}
$r = RefundQueueService::planPayouts(10.0, $queue, 0.10, 0.05);
check('batch = ceil(5 % × 100) = 5', $r['batch'] === 5, "batch={$r['batch']}");
check('paie 5 premiers (FIFO)', array_column($r['payouts'], 'id') === [1, 2, 3, 4, 5]);
check('solde restant = 5', abs($r['remaining'] - 5.0) < 1e-9);

// FIFO strict : le premier coûte 8, le fonds (trigger OK) ne permet qu'un
$queue2 = [['id' => 1, 'amount' => 8.0], ['id' => 2, 'amount' => 1.0], ['id' => 3, 'amount' => 1.0]];
// dette = 10 -> trigger 10 % = 1 ; batch = ceil(5 %×3)=1 ; fonds=8 -> paie le 1er
$r = RefundQueueService::planPayouts(8.0, $queue2, 0.10, 0.05);
check('FIFO : paie la tête de file', array_column($r['payouts'], 'id') === [1]);
check('jamais de solde négatif', $r['remaining'] >= -1e-9, "remaining={$r['remaining']}");

// fonds insuffisant pour la tête -> ne saute pas
$queue3 = [['id' => 1, 'amount' => 100.0], ['id' => 2, 'amount' => 1.0]];
$r = RefundQueueService::planPayouts(50.0, $queue3, 0.10, 0.50);
check('ne saute pas la tête de file si fonds insuffisant', $r['payouts'] === [] && abs($r['remaining'] - 50.0) < 1e-9);

echo "\n=== Multi-remboursement : plusieurs entrées distinctes par joueur ===\n";
// Ordre FIFO global : user1(A=id1), user2(id2), user1(B=id3)
$queueMulti = [
    ['id' => 1, 'user' => 1, 'amount' => 1.0],
    ['id' => 2, 'user' => 2, 'amount' => 1.0],
    ['id' => 3, 'user' => 1, 'amount' => 1.0],
];
$hasTwoForUser1 = count(array_filter($queueMulti, fn ($x) => $x['user'] === 1)) === 2;
check('le même joueur peut avoir 2 remboursements distincts', $hasTwoForUser1 && $queueMulti[0]['id'] !== $queueMulti[2]['id']);

$r = RefundQueueService::planPayouts(10.0, $queueMulti, 0.10, 0.34); // batch = ceil(0.34×3)=2
check('paiement FIFO global (id 1 puis 2)', array_column($r['payouts'], 'id') === [1, 2], json_encode(array_column($r['payouts'], 'id')));

$paidIds = array_column($r['payouts'], 'id');
$remaining = array_values(array_filter($queueMulti, fn ($x) => ! in_array($x['id'], $paidIds, true)));
$r2 = RefundQueueService::planPayouts(10.0, $remaining, 0.10, 0.50);
check('le 2e remboursement (id 3) est payé à son tour', array_column($r2['payouts'], 'id') === [3]);
check('chaque entrée est payée une seule fois (ordre distinct)', count($r2['payouts']) === 1);

echo "\n=== Simulation de cohortes (1000 joueurs/jour, 365 jours) ===\n";

function simulate(float $T, float $feeRate, float $refundPct, int $N = 1000, int $days = 365, float $trigger = 0.10, float $batch = 0.05): array
{
    $t = $T - 1;
    $phi = $feeRate * $T;
    $P = 1 / ($T + $phi);
    $q = (1 - $P) * (1 - $P);
    $X = (1 + $t + $phi) / $t;
    $C = 0.4;
    $R = $C * $X;
    $B = $C + $R;
    $revPer = $phi * $C + (1 - $P) * $phi * $R;
    $refundAmt = $refundPct * $B;

    $fund = 0.0;
    $queue = [];
    $nextId = 1;
    $carry = 0.0;
    $neg = false;
    $fifoOk = true;
    $minFund = INF;
    $snap = [];

    for ($d = 1; $d <= $days; $d++) {
        $fund += $N * $revPer;

        $fails = $N * $q + $carry;
        $n = (int) floor($fails);
        $carry = $fails - $n;
        for ($i = 0; $i < $n; $i++) {
            $queue[] = ['id' => $nextId++, 'amount' => $refundAmt];
        }

        $plan = RefundQueueService::planPayouts($fund, $queue, $trigger, $batch);
        if ($plan['paid'] > 0) {
            $ids = array_column($plan['payouts'], 'id');
            $head = array_slice(array_column($queue, 'id'), 0, count($ids));
            if ($ids !== $head) {
                $fifoOk = false;
            }
            $set = array_flip($ids);
            $queue = array_values(array_filter($queue, fn ($it) => ! isset($set[$it['id']])));
        }

        $fund = $plan['remaining'];
        if ($fund < -1e-9) {
            $neg = true;
        }
        $minFund = min($minFund, $fund);
        if (in_array($d, [30, 180, 365], true)) {
            $snap[$d] = ['queue' => count($queue), 'fund' => $fund, 'paid' => count($plan['payouts'])];
        }
    }

    return [
        'T' => $T, 'fee' => $feeRate, 'refundPct' => $refundPct,
        'coverage' => $revPer / ($q * $B), 'q' => $q, 'B' => $B,
        'snap' => $snap, 'neg' => $neg, 'fifoOk' => $fifoOk, 'minFund' => $minFund,
    ];
}

$scenarios = [
    ['T=1.2  φ=20%  remb=100%', 1.2, 0.20, 1.00, 'bounded'],
    ['T=1.2  φ=2.5% remb=100%', 1.2, 0.025, 1.00, 'grow'],
    ['T=1.2  φ=2.5% remb=26%',  1.2, 0.025, 0.26, 'bounded'],
    ['T=1.05 φ=2.5% remb=58%',  1.05, 0.025, 0.58, 'bounded'],
];

foreach ($scenarios as [$label, $T, $fee, $rp, $expect]) {
    $s = simulate($T, $fee, $rp);
    $q30 = $s['snap'][30]['queue'];
    $q180 = $s['snap'][180]['queue'];
    $q365 = $s['snap'][365]['queue'];
    printf("%-28s couverture=%5.1f%%  q=%.3f%%  file J30=%5d  J180=%6d  J365=%6d  fonds J365=%.2f\n",
        $label, $s['coverage'] * 100, $s['q'] * 100,
        $q30, $q180, $q365, $s['snap'][365]['fund']);

    check("{$label} : fonds jamais négatif", ! $s['neg'], 'min=' . round($s['minFund'], 4));
    check("{$label} : FIFO respecté", $s['fifoOk']);

    if ($expect === 'grow') {
        check("{$label} : la file grossit (couverture < remboursement)", $q365 > $q180 * 1.5, "J180={$q180} → J365={$q365}");
    } else {
        // file de travail bornée : elle n'explose pas entre J180 et J365
        check("{$label} : file bornée/stable", $q365 <= $q180 * 1.2 + 200, "J180={$q180} → J365={$q365}");
        check("{$label} : fonds positif en fin de période", $s['snap'][365]['fund'] >= 0, 'fonds=' . round($s['snap'][365]['fund'], 2));
    }
    echo "\n";
}

echo "=== Résultat : {$pass} OK, {$fail} échec(s) ===\n";
exit($fail === 0 ? 0 : 1);
