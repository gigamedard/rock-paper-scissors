<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Écrit chaque combat dans un Redis Stream (audit brut, hors chemin critique).
 * Un consumer group (job ArchiveFightStream) lit ensuite ce stream et fait le
 * bulk-insert en base + l'archivage IPFS.
 */
class FightStreamWriter
{
    private string $stream;

    public function __construct()
    {
        $this->stream = config('game_engine.fight_stream', 'battlepool:fights');
    }

    public function push(array $fight): void
    {
        try {
            Redis::xadd($this->stream, '*', [
                'pool_id' => (string) ($fight['pool_id'] ?? ''),
                'round' => (string) ($fight['round'] ?? 0),
                'user1_id' => (string) ($fight['user1_id'] ?? ''),
                'user2_id' => (string) ($fight['user2_id'] ?? ''),
                'result' => (string) ($fight['result'] ?? 'draw'),
                'amount' => (string) ($fight['amount'] ?? 0),
                'base_bet' => (string) ($fight['base_bet'] ?? 0),
                'user1_move' => (string) ($fight['user1_move'] ?? ''),
                'user2_move' => (string) ($fight['user2_move'] ?? ''),
                'ts' => (string) now()->getTimestamp(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[FIGHT_STREAM] push failed: ' . $e->getMessage());
        }
    }

    public function stream(): string
    {
        return $this->stream;
    }
}
