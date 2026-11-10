<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Moteur de simulation « full in-memory » (bots P3).
 *
 * - Charge la cohorte P3 en mémoire (état complet par bot).
 * - Joue les pools (5 rounds PFC) sans AUCUNE écriture DB par pool.
 * - Chaque combat part dans le Redis Stream (audit).
 * - Ne persiste en DB qu'aux fins de session (win / double échec) + snapshot.
 *
 * Mode simulation continue : après une session gagnée, le bot repart sur une
 * nouvelle session (équivalent d'un re-dépôt de B), pour un run continu.
 */
class InMemorySimulationService
{
    /** @var array<int,array<string,mixed>> */
    private array $users = [];
    /** @var array<int,array{moves:array<int,string>,idx:int}> */
    private array $premoves = [];
    /** @var array<int,true> */
    private array $dirty = [];

    private int $poolSeq = 0;
    private float $pendingCreditC = 0.0;
    private float $pendingCreditR = 0.0;
    private string $stream;

    public function __construct(private BankrollSessionService $bankroll, private RefundQueueService $refunds)
    {
        $this->stream = config('game_engine.fight_stream', 'battlepool:fights');
    }

    public function loadCohort(int $limit = 1000): int
    {
        $this->users = [];
        $this->premoves = [];

        $T = (float) config('bankroll.session_target_q', 1.2);
        $phi = (float) config('bankroll.fee_rate', 0.10);

        $rows = User::with('preMove')
            ->where('autoplay_active', true)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $u) {
            $baseBet = (float) ($u->initial_base_bet ?? 0.0004);
            $params = $this->bankroll->params($baseBet);
            $C = $params['C'];
            $R = $params['R'];
            $attempt = (int) ($u->session_attempt ?? 0);

            $this->users[$u->id] = [
                'id' => $u->id,
                'wallet' => $u->wallet_address,
                'base_bet' => $baseBet,
                'bet' => (float) ($u->bet_amount ?: $baseBet),
                'target_q' => (float) ($u->target_q ?: $T),
                'T' => $T,
                'phi' => $phi,
                'C' => $C,
                'R' => $R,
                'B' => $params['B'],
                'attempt' => $attempt,
                'cap' => $attempt === 1 ? $R : $C,
                'balance' => $attempt === 1 ? $R : $C,
                'battle' => 0.0,
                'status' => 'available',
                'wins' => 0,
                'fails' => 0,
                'sessionPools' => 0,
                'sessionStart' => microtime(true),
                'lastPools' => 0,
                'lastTime' => 0.0,
            ];

            $moves = $u->preMove->moves ?? ['rock', 'paper', 'scissors'];
            if (! is_array($moves) || empty($moves)) {
                $moves = ['rock', 'paper', 'scissors'];
            }
            $this->premoves[$u->id] = ['moves' => array_values($moves), 'idx' => 0];
        }

        return count($this->users);
    }

    /**
     * Joue jusqu'à $maxPools pools en mémoire (bots disponibles par groupes de 13).
     *
     * @return array{pools:int,fights:int,seconds:float,wins:int,fails:int}
     */
    public function runCycle(int $maxPools = 60): array
    {
        $start = microtime(true);
        $pools = 0;
        $fights = 0;
        $wins = 0;
        $fails = 0;
        $winPools = 0;
        $winTime = 0.0;
        $failPools = 0;
        $failTime = 0.0;

        while ($pools < $maxPools) {
            $available = [];
            foreach ($this->users as $id => $u) {
                if ($u['status'] === 'available' && $u['balance'] >= $u['base_bet']) {
                    $available[] = $id;
                }
            }

            if (count($available) < 13) {
                break;
            }

            // Mélange (simulation) puis pools de 13.
            shuffle($available);
            $chunk = array_slice($available, 0, 13);
            $this->poolSeq++;

            $baseBet = $this->users[$chunk[0]]['base_bet'];
            foreach ($chunk as $id) {
                // Mise du pool = baseBet (comme le vrai battle_balance), PAS le solde total.
                $this->users[$id]['battle'] = $baseBet;
                $this->users[$id]['balance'] -= $baseBet;
                $this->users[$id]['status'] = 'in_pool';
                $this->users[$id]['sessionPools']++;
            }

            for ($round = 1; $round <= 5; $round++) {
                $eligible = [];
                foreach ($chunk as $id) {
                    if ($this->users[$id]['battle'] >= $baseBet - 1e-12) {
                        $eligible[] = $id;
                    }
                }
                if (count($eligible) < 2) {
                    break;
                }
                shuffle($eligible);
                if (count($eligible) % 2 !== 0) {
                    array_pop($eligible);
                }

                for ($i = 0; $i + 1 < count($eligible); $i += 2) {
                    $a = $eligible[$i];
                    $b = $eligible[$i + 1];

                    $mA = $this->nextMove($a);
                    $mB = $this->nextMove($b);
                    $result = $this->pfc($mA, $mB);

                    $amount = 0.0;
                    if ($result === 'user1_win') {
                        $amount = $this->transfer($round, $this->users[$b]['battle'], $baseBet);
                        $this->users[$b]['battle'] -= $amount;
                        $this->users[$a]['battle'] += $amount;
                    } elseif ($result === 'user2_win') {
                        $amount = $this->transfer($round, $this->users[$a]['battle'], $baseBet);
                        $this->users[$a]['battle'] -= $amount;
                        $this->users[$b]['battle'] += $amount;
                    }

                    $fights++;
                    $this->streamFight($a, $b, $round, $result, $amount, $baseBet, $mA, $mB);
                }
            }

            // Fin de pool : consolidation battle -> balance, puis évaluation.
            foreach ($chunk as $id) {
                $this->users[$id]['balance'] += $this->users[$id]['battle'];
                $this->users[$id]['battle'] = 0.0;
                $outcome = $this->evaluateSession($id);
                if ($outcome === 'win') {
                    $wins++;
                    $winPools += $this->users[$id]['lastPools'];
                    $winTime += $this->users[$id]['lastTime'];
                } elseif ($outcome === 'fail') {
                    $fails++;
                    $failPools += $this->users[$id]['lastPools'];
                    $failTime += $this->users[$id]['lastTime'];
                }
            }

            $pools++;
        }

        $this->persistDirty();
        $this->flushCredits();

        return [
            'pools' => $pools,
            'fights' => $fights,
            'seconds' => microtime(true) - $start,
            'wins' => $wins,
            'fails' => $fails,
            'avgPoolsWin' => $wins > 0 ? $winPools / $wins : 0.0,
            'avgTimeWin' => $wins > 0 ? $winTime / $wins : 0.0,
            'avgPoolsFail' => $fails > 0 ? $failPools / $fails : 0.0,
            'avgTimeFail' => $fails > 0 ? $failTime / $fails : 0.0,
        ];
    }

    private function evaluateSession(int $id): string
    {
        $u = &$this->users[$id];
        $cap = $u['cap'] > 0 ? $u['cap'] : $u['C'];
        $q = $u['balance'] / $cap;
        $mult = $u['target_q'] * (1 + $u['phi']);

        if ($q >= $mult) {
            // Session gagnée : on repart sur une nouvelle session (re-dépôt simulé).
            $u['lastPools'] = $u['sessionPools'];
            $u['lastTime'] = microtime(true) - $u['sessionStart'];
            $this->logSession('W', (int) $u['lastPools'], (float) $u['lastTime']);
            $u['wins']++;
            $u['balance'] = $u['C'];
            $u['attempt'] = 0;
            $u['cap'] = $u['C'];
            $u['bet'] = $u['base_bet'];
            $u['status'] = 'available';
            $u['sessionPools'] = 0;
            $u['sessionStart'] = microtime(true);
            $this->dirty[$id] = true;

            return 'win';
        }

        if ($u['balance'] < $u['bet'] - 1e-12) {
            if ((int) $u['attempt'] === 0) {
                // Rattrapage : on libère R (en mémoire).
                $u['balance'] = $u['R'];
                $u['attempt'] = 1;
                $u['cap'] = $u['R'];
                $u['bet'] = $u['base_bet'];
                $u['status'] = 'available';

                return 'recovery';
            }

            // Double échec : compté en mémoire uniquement (option a — pas d'enfile DB
            // ni de crédit fonds dans le sim ; le fonds reste sur le moteur DB/réel).
            $u['lastPools'] = $u['sessionPools'];
            $u['lastTime'] = microtime(true) - $u['sessionStart'];
            $this->logSession('F', (int) $u['lastPools'], (float) $u['lastTime']);
            $u['fails']++;
            $u['balance'] = $u['C'];
            $u['attempt'] = 0;
            $u['cap'] = $u['C'];
            $u['bet'] = $u['base_bet'];
            $u['status'] = 'available';
            $u['sessionPools'] = 0;
            $u['sessionStart'] = microtime(true);
            $this->dirty[$id] = true;

            return 'fail';
        }

        // Continue : le bot rejoue un pool.
        $u['status'] = 'available';

        return 'continue';
    }

    private function persistDirty(): void
    {
        if (empty($this->dirty)) {
            return;
        }

        foreach (array_keys($this->dirty) as $id) {
            if (! isset($this->users[$id])) {
                continue;
            }
            $u = $this->users[$id];
            try {
                User::where('id', $id)->update([
                    'balance' => $u['balance'],
                    'battle_balance' => 0,
                    'bet_amount' => $u['bet'],
                    'status' => 'available',
                    'pool_id' => null,
                    'session_attempt' => $u['attempt'],
                    'session_capital' => $u['cap'],
                    'bankroll_total' => $u['B'],
                    'bankroll_recovery_reserve' => $u['R'],
                    'recovery_factor' => $u['R'] > 0 ? $u['R'] / $u['C'] : null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[SIM] persist failed for user ' . $id . ': ' . $e->getMessage());
            }
        }

        $this->dirty = [];
    }

    /** Crédite le fonds en un seul lot par cycle (F_C session + F_R rattrapage). */
    private function flushCredits(): void
    {
        try {
            if ($this->pendingCreditC > 0) {
                $this->refunds->credit($this->pendingCreditC, 'sim_session_fee_C');
                $this->pendingCreditC = 0.0;
            }
            if ($this->pendingCreditR > 0) {
                $this->refunds->credit($this->pendingCreditR, 'sim_recovery_fee_R');
                $this->pendingCreditR = 0.0;
            }
        } catch (\Throwable $e) {
            Log::warning('[SIM] credit flush failed: ' . $e->getMessage());
        }
    }

    private function logSession(string $outcome, int $pools, float $seconds): void
    {
        try {
            @file_put_contents(
                storage_path('app/sessions.csv'),
                sprintf("%s,%d,%.4f\n", $outcome, $pools, $seconds),
                FILE_APPEND
            );
        } catch (\Throwable $e) {
            // non bloquant
        }
    }

    private function nextMove(int $id): string
    {
        $pm = &$this->premoves[$id];
        $n = count($pm['moves']);
        if ($n === 0) {
            return 'rock';
        }
        $move = $pm['moves'][$pm['idx'] % $n];
        $pm['idx'] = ($pm['idx'] + 1) % $n;

        return $move;
    }

    private function pfc(string $a, string $b): string
    {
        if ($a === $b) {
            return 'draw';
        }
        $beats = ['rock' => 'scissors', 'scissors' => 'paper', 'paper' => 'rock'];

        return ($beats[$a] ?? null) === $b ? 'user1_win' : 'user2_win';
    }

    private function transfer(int $round, float $loserStack, float $baseBet): float
    {
        if ($round === 2 || $round === 3) {
            if ($loserStack <= $baseBet + 1e-9) {
                return max(0.0, $loserStack);
            }

            return $loserStack - $baseBet;
        }

        return max(0.0, min($baseBet, $loserStack));
    }

    private function streamFight(int $a, int $b, int $round, string $result, float $amount, float $baseBet, string $mA, string $mB): void
    {
        try {
            Redis::xadd($this->stream, '*', [
                'pool_id' => 'sim-' . $this->poolSeq,
                'round' => (string) $round,
                'user1_id' => (string) $a,
                'user2_id' => (string) $b,
                'result' => $result,
                'amount' => (string) $amount,
                'base_bet' => (string) $baseBet,
                'user1_move' => $mA,
                'user2_move' => $mB,
                'ts' => (string) now()->getTimestamp(),
            ]);
        } catch (\Throwable $e) {
            // non bloquant
        }
    }

    public function userCount(): int
    {
        return count($this->users);
    }
}
