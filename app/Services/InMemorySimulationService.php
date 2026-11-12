<?php

namespace App\Services;

use App\Jobs\SyncUserBalanceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Moteur de simulation « full in-memory ».
 *
 * - Bots : cohorte P3 chargée en mémoire, pools calculés sans écriture DB par pool,
 *   persistance aux fins de session (comportement historique).
 * - Humains (SIM_INCLUDE_HUMANS=true) : les joueurs UI non-autoplay sont chargés
 *   dynamiquement, joués avec les mêmes pools, et leurs effets de bord humains
 *   (match_found/battle_started/fight_result, signature/claim, session_finished,
 *   cooldown, sync_limits) passent par l'outbox. Leur état P3 est persisté après
 *   chaque pool et à la fin de session.
 *
 * On ne lance PAS ce service en même temps que game:engine + batch-processor.
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

    private bool $includeHumans = false;
    private float $humanRefreshSec = 10.0;
    private float $lastHumanRefresh = 0.0;

    public function __construct(private BankrollSessionService $bankroll, private RefundQueueService $refunds)
    {
        $this->stream = config('game_engine.fight_stream', 'battlepool:fights');
        $this->includeHumans = (bool) config('game_engine.sim_include_humans', false);
        $this->humanRefreshSec = max(1.0, (float) config('game_engine.sim_human_refresh_seconds', 10));
    }

    public function loadCohort(int $limit = 1000): int
    {
        $this->users = [];
        $this->premoves = [];

        $T = (float) config('bankroll.session_target_q', 1.2);
        $phi = (float) config('bankroll.fee_rate', 0.005);

        $rows = User::with('preMove')
            ->where('autoplay_active', true)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $u) {
            $baseBet = (float) ($u->initial_base_bet ?? 0.0004);
            $params = $this->bankroll->params($baseBet, (float) ($u->target_q ?: $T));
            $C = $params['C'];
            $R = $params['R'];
            $attempt = (int) ($u->session_attempt ?? 0);

            $this->users[$u->id] = [
                'id' => $u->id,
                'wallet' => $u->wallet_address,
                'base_bet' => $baseBet,
                'bet' => (float) ($u->bet_amount ?: $baseBet),
                'target_q' => (float) ($u->target_q ?: $T),
                'T' => (float) ($u->target_q ?: $T),
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
                'is_human' => false,
            ];

            $this->premoves[$u->id] = $this->premovesFor($u);
        }

        if ($this->includeHumans) {
            $this->loadHumans();
        }

        return count($this->users);
    }

    /**
     * Joue jusqu'à $maxPools pools en mémoire.
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

        $this->refreshHumans();

        while ($pools < $maxPools) {
            // Grouper par palier de mise (= bet_amount, comme le vrai matchmaking).
            $byTier = [];
            foreach ($this->users as $id => $u) {
                if ($u['status'] === 'available' && $u['balance'] >= $u['bet'] - 1e-12) {
                    $byTier[(string) $u['bet']][] = $id;
                }
            }
            $eligibleTiers = [];
            foreach ($byTier as $tierBet => $ids) {
                if (count($ids) >= 13) {
                    $eligibleTiers[] = [(float) $tierBet, $ids];
                }
            }
            if (empty($eligibleTiers)) {
                break;
            }
            $pick = $eligibleTiers[array_rand($eligibleTiers)];
            $baseBet = $pick[0];
            $tierIds = $pick[1];

            shuffle($tierIds);
            $chunk = array_slice($tierIds, 0, 13);
            $this->poolSeq++;

            foreach ($chunk as $id) {
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
                    $this->handleHumanFight($a, $b, $round, $result, $amount, $baseBet, $mA, $mB);
                }
            }

            // Fin de pool : consolidation battle -> balance, martingale humaine, puis évaluation.
            foreach ($chunk as $id) {
                $endBattle = $this->users[$id]['battle'];
                $this->users[$id]['balance'] += $endBattle;
                $this->users[$id]['battle'] = 0.0;

                if (! empty($this->users[$id]['is_human'])) {
                    $this->applyHumanMartingale($id, $baseBet, $endBattle);
                }

                $outcome = $this->evaluateSession($id);
                if ($outcome === 'win') {
                    $wins++;
                    $winPools += $this->users[$id]['lastPools'] ?? 0;
                    $winTime += $this->users[$id]['lastTime'] ?? 0.0;
                } elseif ($outcome === 'fail') {
                    $fails++;
                    $failPools += $this->users[$id]['lastPools'] ?? 0;
                    $failTime += $this->users[$id]['lastTime'] ?? 0.0;
                }
            }

            $pools++;
        }

        $this->persistDirty();
        $this->flushCredits();
        $this->removeRetiredHumans();

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
            'recoveries' => count(array_filter($this->users, fn ($u) => ((int) $u['attempt']) === 1)),
            'humans' => count(array_filter($this->users, fn ($u) => ! empty($u['is_human']))),
        ];
    }

    // ------------------------------------------------------------------
    // Humains : chargement dynamique + persistance + effets de bord
    // ------------------------------------------------------------------

    private function refreshHumans(): void
    {
        if (! $this->includeHumans) {
            return;
        }
        $now = microtime(true);
        if ($now - $this->lastHumanRefresh < $this->humanRefreshSec) {
            return;
        }
        $this->lastHumanRefresh = $now;
        $this->loadHumans();
    }

    private function loadHumans(): void
    {
        $rows = User::with('preMove')
            ->where('autoplay_active', false)
            ->whereNotNull('initial_base_bet')
            ->whereNotNull('target_q')
            ->where('email', 'not like', 'bot_%')
            ->where('status', 'available')
            ->where(function ($q) {
                $q->whereNotNull('bankroll_total')->orWhere('balance', '>', 0);
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($rows as $u) {
            $this->addHuman($u);
        }
    }

    private function addHuman(User $u): bool
    {
        if (isset($this->users[$u->id])) {
            return false;
        }

        $baseBet = (float) ($u->initial_base_bet ?: 0.0004);
        if ($baseBet <= 0) {
            return false;
        }
        $target = (float) ($u->target_q ?: config('bankroll.session_target_q', 1.2));

        if ($u->bankroll_total === null) {
            $params = $this->bankroll->params($baseBet, $target);
            if ((float) $u->balance + 1e-9 < (float) $params['B']) {
                return false; // dépôt insuffisant pour P3
            }
            try {
                $this->bankroll->initialize($u, $baseBet, $target);
                $u->refresh();
            } catch (\Throwable $e) {
                Log::warning('[SIM-HUMAN] initialize failed for user ' . $u->id . ': ' . $e->getMessage());
                return false;
            }
        }

        $attempt = (int) ($u->session_attempt ?? 0);
        $C = (float) ($u->session_capital ?: ($baseBet * (int) config('bankroll.security_coefficient', 100)));
        $R = (float) ($u->bankroll_recovery_reserve ?: ($u->bankroll_remaining ?? 0));
        $B = (float) ($u->bankroll_total ?: ($C + $R));
        $cap = $attempt === 1 ? $R : $C;
        $balance = (float) $u->balance;
        if ($balance <= 0) {
            return false;
        }
        $bet = (float) ($u->bet_amount ?: $baseBet);
        if ($bet <= 0) {
            $bet = $baseBet;
        }

        $this->users[$u->id] = [
            'id' => $u->id,
            'wallet' => $u->wallet_address,
            'base_bet' => $baseBet,
            'bet' => $bet,
            'target_q' => $target,
            'T' => $target,
            'phi' => (float) config('bankroll.fee_rate', 0.005),
            'C' => $C,
            'R' => $R,
            'B' => $B,
            'attempt' => $attempt,
            'cap' => $cap,
            'balance' => $balance,
            'battle' => 0.0,
            'status' => 'available',
            'wins' => 0,
            'fails' => 0,
            'sessionPools' => 0,
            'sessionStart' => microtime(true),
            'lastPools' => 0,
            'lastTime' => 0.0,
            'is_human' => true,
        ];
        $this->premoves[$u->id] = $this->premovesFor($u);

        try {
            DB::table('users')->where('id', $u->id)->update([
                'status' => 'available',
                'session_started' => true,
                'pool_id' => null,
                'balance' => $balance,
                'battle_balance' => 0,
                'bet_amount' => $bet,
                'session_attempt' => $attempt,
                'session_capital' => $cap,
                'session_start_balance' => $cap,
                'session_start_battle_balance' => 0,
                'bankroll_total' => $B,
                'bankroll_remaining' => $R,
                'bankroll_recovery_reserve' => $R,
                'recovery_factor' => $C > 0 ? ($R / $C) : null,
                'target_q' => $target,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SIM-HUMAN] persist load failed for user ' . $u->id . ': ' . $e->getMessage());
        }

        Log::info("[SIM-HUMAN] loaded user {$u->id} ({$u->wallet_address}) C={$C} R={$R} balance={$balance} bet={$bet} target={$target}");
        return true;
    }

    private function premovesFor(User $u): array
    {
        $moves = $u->preMove->moves ?? ['rock', 'paper', 'scissors'];
        if (! is_array($moves) || empty($moves)) {
            $moves = ['rock', 'paper', 'scissors'];
        }
        return ['moves' => array_values($moves), 'idx' => 0];
    }

    private function handleHumanFight(int $a, int $b, int $round, string $result, float $amount, float $baseBet, string $mA, string $mB): void
    {
        $aHuman = ! empty($this->users[$a]['is_human']);
        $bHuman = ! empty($this->users[$b]['is_human']);
        if (! $aHuman && ! $bHuman) {
            return;
        }

        $fightId = $this->insertHumanFight($a, $b, $round, $result, $amount, $baseBet, $mA, $mB);
        $outbox = app(SideEffectOutbox::class);

        if ($aHuman) {
            $this->pushHumanFightEvents($outbox, $a, $b, $fightId, $round, $result, $amount, $mA, $mB, true);
        }
        if ($bHuman) {
            $this->pushHumanFightEvents($outbox, $b, $a, $fightId, $round, $result, $amount, $mB, $mA, false);
        }
    }

    private function pushHumanFightEvents(SideEffectOutbox $outbox, int $id, int $opponentId, int $fightId, int $round, string $result, float $amount, string $myMove, string $opponentMove, bool $isUser1): void
    {
        $myBalance = (float) $this->users[$id]['balance'] + (float) $this->users[$id]['battle'];
        if ($result === 'draw') {
            $outcome = 'draw';
            $delta = 0.0;
        } elseif (($isUser1 && $result === 'user1_win') || (! $isUser1 && $result === 'user2_win')) {
            $outcome = 'win';
            $delta = $amount;
        } else {
            $outcome = 'loss';
            $delta = -$amount;
        }

        try {
            $outbox->push($id, 'match_found', ['fight_id' => $fightId, 'opponent' => (string) ($this->users[$opponentId]['wallet'] ?? '')]);
            $outbox->push($id, 'battle_started', ['fight_id' => $fightId, 'opponent_id' => $opponentId, 'pool_id' => 0]);
            $outbox->push($id, 'fight_result', [
                'result' => $outcome,
                'delta' => (string) $delta,
                'balance' => (string) $myBalance,
                'my_move' => $myMove,
                'opponent_move' => $opponentMove,
                'round' => $round,
                'opponent' => (string) ($this->users[$opponentId]['wallet'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SIM-HUMAN] outbox fight push failed for user ' . $id . ': ' . $e->getMessage());
        }
    }

    private function insertHumanFight(int $a, int $b, int $round, string $result, float $amount, float $baseBet, string $mA, string $mB): int
    {
        try {
            return (int) DB::table('fights')->insertGetId([
                'user1_id' => $a,
                'user2_id' => $b,
                'status' => 'completed',
                'result' => $result,
                'base_bet_amount' => $baseBet,
                'transferred_amount' => $amount,
                'max_bet_amount' => 0,
                'user1_chosed' => $mA,
                'user2_chosed' => $mB,
                'pool_id' => null,
                'round' => $round,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SIM-HUMAN] fight insert failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function applyHumanMartingale(int $id, float $baseBet, float $endBattle): void
    {
        $u = &$this->users[$id];
        $maxLevel = (int) config('pool.max_martingale_level', 4);
        if ($endBattle < $baseBet - 1e-9) {
            $next = (float) $u['bet'] * 2;
            $max = (float) $u['base_bet'] * (2 ** $maxLevel);
            $u['bet'] = $next > $max ? (float) $u['base_bet'] : $next;
        } elseif ($endBattle > $baseBet + 1e-9) {
            $u['bet'] = (float) $u['base_bet'];
        }
    }

    private function persistHuman(int $id, string $phase): void
    {
        $u = $this->users[$id] ?? null;
        if (! $u) {
            return;
        }
        try {
            DB::table('users')->where('id', $id)->update([
                'balance' => $u['balance'],
                'battle_balance' => 0,
                'bet_amount' => $u['bet'],
                'status' => 'available',
                'pool_id' => null,
                'session_started' => true,
                'session_attempt' => $u['attempt'],
                'session_capital' => $u['cap'],
                'session_start_balance' => $u['cap'],
                'session_start_battle_balance' => 0,
                'bankroll_total' => $u['B'],
                'bankroll_remaining' => $u['R'],
                'bankroll_recovery_reserve' => $u['R'],
                'recovery_factor' => $u['C'] > 0 ? ($u['R'] / $u['C']) : null,
                'target_q' => $u['target_q'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SIM-HUMAN] persist ' . $phase . ' failed for user ' . $id . ': ' . $e->getMessage());
        }
    }

    private function completeHumanSession(int $id, string $outcome, float $finalBalance, float $q): void
    {
        $u = $this->users[$id];
        try {
            $user = User::find($id);
            if (! $user) {
                return;
            }

            $cooldownMinutes = (int) ($user->cooldown_time ?: config('economy.cooldowns.0.0004', 4320));

            if ($outcome === 'win') {
                $user->balance = $finalBalance;
                $user->battle_balance = 0;
                $user->status = 'stopped';
                $user->bankroll_total = null;
                $user->bankroll_remaining = 0;
                $user->bankroll_recovery_reserve = $u['R'];
                $user->session_attempt = 0;
                $user->session_capital = $u['C'];
                $user->session_started = false;
                $user->session_start_balance = 0;
                $user->session_start_battle_balance = 0;
                $user->bet_amount = $u['base_bet'];
                $user->pool_id = null;
                $user->payout_signature = null;
                $user->cooldown_until = now()->addMinutes($cooldownMinutes);
                $user->save();

                $outbox = app(SideEffectOutbox::class);
                $outbox->push($id, 'session_finished', ['status' => 'SUCCESS', 'q' => (string) $q, 'payout_triggered' => false, 'signature' => null]);
                $outbox->push($id, 'sync_limits', []);
                $outbox->push($id, 'cooldown', ['wallet' => $user->wallet_address, 'next_time' => $user->cooldown_until->timestamp]);
                $outbox->push($id, 'session_history', []);

                SyncUserBalanceJob::dispatch($id);
            } else {
                // Double échec : dette de remboursement AVANT de nettoyer bankroll_total.
                try {
                    $this->bankroll->enqueueRefund($user);
                } catch (\Throwable $e) {
                    Log::warning('[SIM-HUMAN] refund enqueue failed for user ' . $id . ': ' . $e->getMessage());
                }

                $user->balance = $finalBalance;
                $user->battle_balance = 0;
                $user->status = 'stopped';
                $user->bankroll_total = null;
                $user->bankroll_remaining = 0;
                $user->session_attempt = 0;
                $user->session_started = false;
                $user->session_start_balance = 0;
                $user->session_start_battle_balance = 0;
                $user->bet_amount = $u['base_bet'];
                $user->pool_id = null;
                $user->payout_signature = null;
                $user->cooldown_until = now()->addMinutes($cooldownMinutes);
                $user->save();

                $outbox = app(SideEffectOutbox::class);
                $outbox->push($id, 'session_finished', ['status' => 'FAIL', 'q' => (string) $q, 'payout_triggered' => false, 'signature' => null]);
                $outbox->push($id, 'sync_limits', []);
                $outbox->push($id, 'cooldown', ['wallet' => $user->wallet_address, 'next_time' => $user->cooldown_until->timestamp]);
                $outbox->push($id, 'session_history', []);

                if ($finalBalance > 0) {
                    SyncUserBalanceJob::dispatch($id);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[SIM-HUMAN] complete session failed for user ' . $id . ': ' . $e->getMessage());
        }

        // On le retire de la cohorte après la boucle courante.
        $this->users[$id]['status'] = 'stopped';
        $this->users[$id]['retire'] = true;
        $this->dirty[$id] = false;
    }

    private function removeRetiredHumans(): void
    {
        foreach ($this->users as $id => $u) {
            if (! empty($u['retire'])) {
                unset($this->users[$id], $this->premoves[$id], $this->dirty[$id]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Session (bots + humains)
    // ------------------------------------------------------------------

    private function evaluateSession(int $id): string
    {
        $u = &$this->users[$id];
        $isHuman = ! empty($u['is_human']);
        $cap = $u['cap'] > 0 ? $u['cap'] : $u['C'];
        $q = $u['balance'] / $cap;
        $mult = $u['target_q'] * (1 + $u['phi']);

        if ($q >= $mult) {
            $u['lastPools'] = $u['sessionPools'];
            $u['lastTime'] = microtime(true) - $u['sessionStart'];
            $this->logSession('W', (int) $u['lastPools'], (float) $u['lastTime']);
            $u['wins']++;

            if ($isHuman) {
                // Session 1 : le payout inclut la réserve R. Rattrapage : R est déjà le capital joué.
                $final = (float) $u['balance'] + ((int) $u['attempt'] === 0 ? (float) $u['R'] : 0.0);
                $this->completeHumanSession($id, 'win', $final, $q);
                return 'win';
            }

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
                $x = $u['C'] > 0 ? ($u['R'] / $u['C']) : 1.0;
                $u['balance'] = $u['R'];
                $u['attempt'] = 1;
                $u['cap'] = $u['R'];
                $u['bet'] = $u['base_bet'] * $x;
                $u['status'] = 'available';

                if ($isHuman) {
                    $this->persistHuman($id, 'recovery');
                }

                return 'recovery';
            }

            $u['lastPools'] = $u['sessionPools'];
            $u['lastTime'] = microtime(true) - $u['sessionStart'];
            $this->logSession('F', (int) $u['lastPools'], (float) $u['lastTime']);
            $u['fails']++;

            if ($isHuman) {
                $this->completeHumanSession($id, 'fail', (float) $u['balance'], $q);
                return 'fail';
            }

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

        $u['status'] = 'available';
        if ($isHuman) {
            $this->persistHuman($id, 'continue');
        }

        return 'continue';
    }

    private function persistDirty(): void
    {
        if (empty($this->dirty)) {
            return;
        }

        foreach (array_keys($this->dirty) as $id) {
            if (! isset($this->users[$id]) || $this->users[$id]['is_human']) {
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
