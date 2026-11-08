<?php
/**
 * Validation de la state machine P3 (bankroll + session + rattrapage).
 * Exécution : php tests/bankroll_state_machine_test.php
 * Teste la logique pure (aucune base de données).
 */

require __DIR__ . '/../app/Services/BankrollSessionService.php';

use App\Services\BankrollSessionService;

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

$C = 0.4;
$baseBet = 0.0004;
$security = 1000;
$refundPct = 0.25;

echo "=== Paramètres / formules ===\n";
foreach ([1.05, 1.2] as $T) {
    $phi = 0.025;
    $p = BankrollSessionService::computeParams($baseBet, $T, $phi, $security, $refundPct);
    $t = $T - 1;
    $phiUsed = $phi * $T;
    $Xexp = (1 + $t + $phiUsed) / $t;
    check("T={$T} : C = baseBet×security", abs($p['C'] - $C) < 1e-12, "C={$p['C']}");
    check("T={$T} : X = (1+t+φ_used)/t", abs($p['X'] - $Xexp) < 1e-9, "X=" . round($p['X'], 4));
    check("T={$T} : B = C + R", abs($p['B'] - ($p['C'] + $p['R'])) < 1e-9, "B=" . round($p['B'], 4));
    check("T={$T} : seuil_1 = C·T·(1+φ)", abs($p['threshold_1'] - $C * $T * (1 + $phi)) < 1e-9);
    check("T={$T} : gain net gagnant = C·t", abs(($p['threshold_1'] - $p['C']) - $p['F_C'] - $C * $t) < 1e-9, "net=" . round($C * $t, 4));
    echo "\n";
}

echo "=== Activation gardée (dépôt B = C+R) ===\n";
$p = BankrollSessionService::computeParams($baseBet, 1.2, 0.025, $security, $refundPct);
check('solde >= B → activable', BankrollSessionService::canActivate($p['B'], $p));
check('solde = C seul → NON activable', ! BankrollSessionService::canActivate($p['C'], $p));
check('solde = B−epsilon → NON activable', ! BankrollSessionService::canActivate($p['B'] - 0.001, $p));

echo "\n=== Transitions nextAction ===\n";
$T = 1.2;
$phi = 0.025;
$p = BankrollSessionService::computeParams($baseBet, $T, $phi, $security, $refundPct);
$state1 = ['attempt' => 0, 'capital' => $p['C'], 'T' => $T, 'phi' => $phi];
$state2 = ['attempt' => 1, 'capital' => $p['R'], 'T' => $T, 'phi' => $phi];
check('solde = seuil session 1 → win', BankrollSessionService::nextAction($state1, $p['threshold_1'], $baseBet) === 'win');
check('solde légèrement < seuil et ≥ mise → continue', BankrollSessionService::nextAction($state1, $p['threshold_1'] * 0.5, $baseBet) === 'continue');
check('ruine en session 1 → recovery', BankrollSessionService::nextAction($state1, 0.0, $baseBet) === 'recovery');
check('ruine en rattrapage → double_failure', BankrollSessionService::nextAction($state2, 0.0, $baseBet) === 'double_failure');
check('seuil rattrapage plus grand (R > C)', $p['threshold_2'] > $p['threshold_1'], 'seuil2=' . round($p['threshold_2'], 3));

echo "\n=== Monte Carlo de la state machine (50 000 joueurs) ===\n";
function simulate(int $N, float $baseBet, float $T, float $phi, int $security, float $refundPct): array
{
    $p = BankrollSessionService::computeParams($baseBet, $T, $phi, $security, $refundPct);
    $P = 1.0 / ($T * (1.0 + $phi));
    $netWin = $p['C'] * ($T - 1.0);
    $netLoss = -(1.0 - $refundPct) * $p['B'];

    $w1 = 0; $wr = 0; $fail2 = 0; $sumNet = 0; $liability = 0.0; $neg = false;

    for ($i = 0; $i < $N; $i++) {
        $balance = $p['C'];
        $state = ['attempt' => 0, 'capital' => $p['C'], 'T' => $T, 'phi' => $phi];

        // session 1 : issue par marche aléatoire (probabilité exacte de la ruine)
        $action = (mt_rand() / mt_getrandmax() < $P)
            ? BankrollSessionService::nextAction($state, $p['threshold_1'], $baseBet)
            : BankrollSessionService::nextAction($state, 0.0, $baseBet);

        if ($action === 'win') {
            $w1++;
            $sumNet += $netWin;
            continue;
        }

        // rattrapage
        $state = ['attempt' => 1, 'capital' => $p['R'], 'T' => $T, 'phi' => $phi];
        $action = (mt_rand() / mt_getrandmax() < $P)
            ? BankrollSessionService::nextAction($state, $p['threshold_2'], $baseBet)
            : BankrollSessionService::nextAction($state, 0.0, $baseBet);

        if ($action === 'win') {
            $wr++;
            $sumNet += $netWin;
        } else {
            $fail2++;
            $sumNet += $netLoss;
            $liability += $p['refund_amount'];
        }
    }

    return [
        'P' => $P,
        'q' => $fail2 / $N,
        'qExp' => (1 - $P) * (1 - $P),
        'w1' => $w1 / $N, 'wr' => $wr / $N, 'fail2' => $fail2 / $N,
        'netWin' => $netWin, 'netLoss' => $netLoss,
        'avgNet' => $sumNet / $N, 'liabilityPerPlayer' => $liability / $N,
        'params' => $p,
    ];
}

foreach ([1.05, 1.2] as $T) {
    $r = simulate(50000, $baseBet, $T, 0.025, $security, $refundPct);
    $p = $r['params'];
    printf("T=%.2f | P=%.4f | q=%.4f%% (théorie %.4f%%) | S1=%.2f%% rat=%.2f%% échec2=%.3f%% | gain=%+.4f perte=%+.4f | net moy=%+.4f | dette/remb=%+.4f\n",
        $T, $r['P'], $r['q'] * 100, $r['qExp'] * 100, $r['w1'] * 100, $r['wr'] * 100, $r['fail2'] * 100,
        $r['netWin'], $r['netLoss'], $r['avgNet'], $r['liabilityPerPlayer']);

    check("T={$T} : q empirique ≈ (1−P)²", abs($r['q'] - $r['qExp']) < 0.003);
    check("T={$T} : gain gagnant positif (+C·t)", $r['netWin'] > 0, 'gain=' . round($r['netWin'], 4));
    check("T={$T} : remboursement par échec = refund%·B", abs($r['params']['refund_amount'] - $refundPct * $r['params']['B']) < 1e-9);
    echo "\n";
}

echo "=== Résultat : {$pass} OK, {$fail} échec(s) ===\n";
exit($fail === 0 ? 0 : 1);
