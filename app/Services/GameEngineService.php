<?php

namespace App\Services;

use App\Models\Fight;
use App\Models\Pool;
use App\Models\PreMove;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Moteur de jeu « en mémoire » : traite une pool (13 joueurs, 5 rounds) sans
 * aucune écriture DB par combat. Les combats partent dans un Redis Stream
 * (audit asynchrone), les soldes finaux sont persistés en une fois, puis le
 * SessionManager fait la martingale/fin de session.
 */
class GameEngineService
{
    private int $sinceFlush = 0;

    public function __construct(
        private FightStreamWriter $streamWriter,
        private GameEventBuffer $eventBuffer,
        private SessionManager $sessionManager,
        private \App\Helpers\Web3Helper $web3Helper,
    ) {
    }

    /**
     * Traite jusqu'à `batch_pools` pools en attente puis flush les events.
     */
    public function tick(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('game_engine.batch_pools', 20);

        $pools = Pool::where('status', 'from_server_waitting')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pools as $pool) {
            try {
                $this->processPoolInMemory($pool);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("[ENGINE] Pool {$pool->id} failed: " . $e->getMessage());
                $pool->update(['status' => 'from_server_finished']);
                $this->sessionManager->evaluatePoolEnd($pool);
            }
        }

        $batches = $this->eventBuffer->flush();
        $this->sinceFlush = 0;

        return ['pools' => $pools->count(), 'event_batches' => $batches];
    }

    public function processPoolInMemory(Pool $pool): void
    {
        $baseBet = (float) $pool->base_bet;
        $users = $pool->users()->where('status', 'in_pool')->get()->keyBy('id');

        if ($users->count() < 2) {
            $pool->update(['status' => 'from_server_finished']);
            $this->sessionManager->evaluatePoolEnd($pool);
            $this->afterPool();
            return;
        }

        /** @var Collection $preMoves */
        $preMoves = PreMove::whereIn('user_id', $users->keys())->get()->keyBy('user_id');
        $cursor = [];
        foreach ($preMoves as $uid => $pm) {
            $cursor[$uid] = (int) $pm->current_index;
        }

        $fights = [];
        $rounds = (int) config('pool.rounds', 5);
        $now = now();

        for ($round = 1; $round <= $rounds; $round++) {
            $eligible = $users
                ->filter(fn ($u) => (float) $u->battle_balance >= $baseBet - 1e-12)
                ->values();

            if ($eligible->count() < 2) {
                break;
            }

            // Appariement déterministe salé (identique au MatchmakingEngine)
            // pour garantir l'équitabilité vérifiable.
            $dynamicSalt = hash('sha256', $pool->salt . ($round - 1));
            $sortedAddresses = $this->web3Helper->sortAddressesWithSalt(
                $eligible->pluck('wallet_address')->toArray(),
                $dynamicSalt
            );
            $eligible = $eligible->sortBy(function ($user) use ($sortedAddresses) {
                return array_search($user->wallet_address, $sortedAddresses);
            })->values();

            if ($eligible->count() % 2 !== 0) {
                $eligible->pop(); // bye
            }

            for ($i = 0; $i + 1 < $eligible->count(); $i += 2) {
                /** @var User $a */
                $a = $eligible[$i];
                /** @var User $b */
                $b = $eligible[$i + 1];

                $moveA = $this->nextMove($preMoves[$a->id] ?? null, $cursor, $a->id);
                $moveB = $this->nextMove($preMoves[$b->id] ?? null, $cursor, $b->id);
                $result = $this->pfc($moveA, $moveB);

                $amount = 0.0;
                if ($result === 'user1_win') {
                    $amount = $this->computeRoundTransfer($round, (float) $b->battle_balance, $baseBet);
                    $b->battle_balance = (float) $b->battle_balance - $amount;
                    $a->battle_balance = (float) $a->battle_balance + $amount;
                } elseif ($result === 'user2_win') {
                    $amount = $this->computeRoundTransfer($round, (float) $a->battle_balance, $baseBet);
                    $a->battle_balance = (float) $a->battle_balance - $amount;
                    $b->battle_balance = (float) $b->battle_balance + $amount;
                }

                $fights[] = [
                    'user1_id' => $a->id,
                    'user2_id' => $b->id,
                    'pool_id' => $pool->id,
                    'status' => 'completed',
                    'result' => $result,
                    'user1_chosed' => $moveA,
                    'user2_chosed' => $moveB,
                    'base_bet_amount' => $baseBet,
                    'max_bet_amount' => 0,
                    'round' => $round,
                    'transferred_amount' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $this->streamWriter->push([
                    'pool_id' => $pool->id,
                    'round' => $round,
                    'user1_id' => $a->id,
                    'user2_id' => $b->id,
                    'result' => $result,
                    'amount' => $amount,
                    'base_bet' => $baseBet,
                    'user1_move' => $moveA,
                    'user2_move' => $moveB,
                ]);

                // Events (humains uniquement), accumulés pour un envoi groupé.
                if (! $a->isBot()) {
                    $this->eventBuffer->add($a->id, [
                        'type' => 'fight',
                        'round' => $round,
                        'result' => $result === 'user1_win' ? 'win' : ($result === 'user2_win' ? 'loss' : 'draw'),
                        'delta' => $result === 'user1_win' ? $amount : ($result === 'user2_win' ? -$amount : 0),
                        'opponent' => $b->wallet_address,
                        'my_move' => $moveA,
                        'opponent_move' => $moveB,
                        'balance' => (float) $a->balance,
                    ]);
                }
                if (! $b->isBot()) {
                    $this->eventBuffer->add($b->id, [
                        'type' => 'fight',
                        'round' => $round,
                        'result' => $result === 'user2_win' ? 'win' : ($result === 'user1_win' ? 'loss' : 'draw'),
                        'delta' => $result === 'user2_win' ? $amount : ($result === 'user1_win' ? -$amount : 0),
                        'opponent' => $a->wallet_address,
                        'my_move' => $moveB,
                        'opponent_move' => $moveA,
                        'balance' => (float) $b->balance,
                    ]);
                }
            }
        }

        // 1 seule écriture batch pour tous les combats de la pool
        if (! empty($fights)) {
            Fight::insert($fights);
        }

        // 1 seule sauvegarde par joueur (soldes de bataille finaux)
        foreach ($users as $u) {
            $u->save();
        }

        // Persiste l'avancement des premoves (1 par joueur)
        foreach ($preMoves as $uid => $pm) {
            $pm->current_index = $cursor[$uid] ?? 0;
            $pm->save();
        }

        $pool->update(['status' => 'from_server_finished']);

        // Martingale de pool + évaluation de session (logique existante)
        $this->sessionManager->evaluatePoolEnd($pool);

        $this->afterPool();
    }

    private function afterPool(): void
    {
        $this->sinceFlush++;
        $batch = (int) config('game_engine.batch_pools', 20);
        if ($this->sinceFlush >= $batch) {
            $this->eventBuffer->flush();
            $this->sinceFlush = 0;
        }
    }

    private function nextMove(?PreMove $pm, array &$cursor, int $userId): string
    {
        if (! $pm) {
            return 'rock';
        }
        $moves = $pm->moves;
        if (! is_array($moves) || empty($moves)) {
            return 'rock';
        }
        $idx = ($cursor[$userId] ?? 0) % count($moves);
        $cursor[$userId] = ($idx + 1) % count($moves);

        return $moves[$idx];
    }

    private function pfc(string $a, string $b): string
    {
        if ($a === $b) {
            return 'draw';
        }
        $beats = ['rock' => 'scissors', 'scissors' => 'paper', 'paper' => 'rock'];

        return ($beats[$a] ?? null) === $b ? 'user1_win' : 'user2_win';
    }

    private function computeRoundTransfer(int $round, float $loserStack, float $baseBet): float
    {
        if ($round === 2 || $round === 3) {
            if ($loserStack <= $baseBet + 1e-9) {
                return max(0.0, $loserStack);
            }

            return $loserStack - $baseBet;
        }

        return max(0.0, min($baseBet, $loserStack));
    }
}
