<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Outbox asynchrone des effets de bord « humains ».
 *
 * La boucle de jeu pousse un événement (1 INSERT) puis continue immédiatement.
 * Un consumer (`outbox:process`, queue-worker ou service tiers) exécute le vrai
 * effet : payout on-chain, notification, signature, syncLimits, cooldown, broadcast.
 *
 * → La vitesse de jeu des humains devient identique à celle des bots.
 */
class SideEffectOutbox
{
    public const TABLE = 'side_effect_outbox';

    public function push(?int $userId, string $type, array $payload = [], int $delaySeconds = 0): int
    {
        $now = now();

        return (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $userId,
            'type' => $type,
            'payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $now->copy()->addSeconds(max(0, $delaySeconds)),
            'created_at' => $now,
        ]);
    }

    /**
     * Traite un lot d'effets de bord échus.
     *
     * @return array{processed:int,done:int,failed:int}
     */
    public function processBatch(int $limit = 200): array
    {
        $rows = DB::table(self::TABLE)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $done = 0;
        $failed = 0;

        foreach ($rows as $row) {
            DB::table(self::TABLE)->where('id', $row->id)->update(['status' => 'processing']);
            try {
                $this->handle($row);
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'status' => 'done',
                    'processed_at' => now(),
                ]);
                $done++;
            } catch (\Throwable $e) {
                $attempts = (int) $row->attempts + 1;
                $final = $attempts >= 5;
                DB::table(self::TABLE)->where('id', $row->id)->update([
                    'status' => $final ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'available_at' => now()->addSeconds(min(300, $attempts * 30)),
                ]);
                $failed++;
                Log::warning("[OUTBOX] {$row->type} #{$row->id} failed (attempt {$attempts}): " . $e->getMessage());
            }
        }

        return ['processed' => $rows->count(), 'done' => $done, 'failed' => $failed];
    }

    private function handle(object $row): void
    {
        $payload = json_decode($row->payload ?? '[]', true) ?: [];

        switch ($row->type) {
            case 'payout':
                \App\Jobs\ProcessPayoutJob::dispatch(
                    (string) ($payload['wallet'] ?? ''),
                    (float) ($payload['amount'] ?? 0)
                );
                break;

            case 'sync_limits':
                $user = User::find($row->user_id);
                if ($user) {
                    \App\Jobs\SyncUserLimitsJob::dispatch($user)->onQueue('limits');
                }
                break;

            case 'cooldown':
                \App\Jobs\SetCooldownJob::dispatch(
                    (string) ($payload['wallet'] ?? ''),
                    (int) ($payload['next_time'] ?? 0)
                );
                break;

            case 'notification':
                \App\Models\GameNotification::create([
                    'user_id' => $row->user_id,
                    'type' => (string) ($payload['type'] ?? 'INFO'),
                    'data' => $payload['data'] ?? [],
                ]);
                break;

            case 'session_finished':
                $user = User::find($row->user_id);
                if ($user) {
                    \App\Helpers\BroadcastHelper::safe(
                        fn () => event(new \App\Events\SessionFinished(
                            $user,
                            (string) ($payload['status'] ?? 'INFO'),
                            (string) ($payload['q'] ?? '0'),
                            (bool) ($payload['payout_triggered'] ?? false),
                            $payload['signature'] ?? null
                        )),
                        "outbox SessionFinished user {$row->user_id}"
                    );
                }
                break;

            case 'fight_result':
                $user = User::find($row->user_id);
                if ($user) {
                    \App\Helpers\BroadcastHelper::safe(
                        fn () => event(new \App\Events\FightResult(
                            $user,
                            (string) ($payload['result'] ?? 'draw'),
                            (string) ($payload['delta'] ?? '0'),
                            (string) ($payload['balance'] ?? '0'),
                            $payload['my_move'] ?? null,
                            $payload['opponent_move'] ?? null,
                            (int) ($payload['round'] ?? 1)
                        )),
                        "outbox FightResult user {$row->user_id}"
                    );
                }
                break;

            case 'match_found':
                $user = User::find($row->user_id);
                if ($user) {
                    \App\Helpers\BroadcastHelper::safe(
                        fn () => event(new \App\Events\MatchFound($user, (int) ($payload['fight_id'] ?? 0), (string) ($payload['opponent'] ?? ''))),
                        "outbox MatchFound user {$row->user_id}"
                    );
                }
                break;

            case 'battle_started':
                \App\Models\GameNotification::create([
                    'user_id' => $row->user_id,
                    'type' => 'BATTLE_STARTED',
                    'data' => [
                        'fight_id' => (int) ($payload['fight_id'] ?? 0),
                        'opponent_id' => (int) ($payload['opponent_id'] ?? 0),
                        'pool_id' => (int) ($payload['pool_id'] ?? 0),
                    ],
                ]);
                break;

            case 'session_history':
                $user = User::find($row->user_id);
                if ($user) {
                    app(\App\Services\SessionHistoryService::class)->archiveSessionHistory($user);
                }
                break;

            case 'archive_pool':
                $poolId = (int) ($payload['pool_id'] ?? 0);
                if ($poolId > 0) {
                    app(\App\Services\HistoricalFightService::class)->archivePoolFights($poolId);
                }
                break;

            default:
                Log::warning("[OUTBOX] type inconnu: {$row->type}");
        }
    }
}
