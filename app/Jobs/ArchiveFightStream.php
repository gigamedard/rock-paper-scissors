<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Consumer d'audit du Redis Stream des combats.
 *
 * L'engine persiste déjà les combats en base (bulk insert). Ce job consomme le
 * stream pour produire une archive JSONL immuable (audit / équitabilité) et
 * acquitte les entrées. Il est totalement découplé du chemin critique du jeu :
 * s'il prend du retard, le stream bufferise et le jeu continue.
 */
class ArchiveFightStream implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;

    public function handle(): void
    {
        $stream = config('game_engine.fight_stream', 'battlepool:fights');
        $group = config('game_engine.consumer_group', 'battlepool-archive');
        $consumer = config('game_engine.consumer_name', 'worker');
        $count = (int) config('game_engine.archive_batch', 1000);
        $dir = config('game_engine.archive_dir', storage_path('app/fights-archive'));

        $this->ensureGroup($stream, $group);

        $messages = Redis::xreadgroup(
            $group,
            $consumer,
            [$stream => '>'],
            $count,
            1000
        );

        if (empty($messages) || ! is_array($messages)) {
            return;
        }

        // phpredis renvoie les clés de stream AVEC le préfixe Laravel
        // (laravel_database_...), donc on ne peut pas indexer par $stream.
        // On prend le premier stream du résultat.
        $entries = reset($messages);
        if (empty($entries) || ! is_array($entries)) {
            return;
        }

        $this->appendArchive($dir, $entries);

        $ids = [];
        foreach (array_keys($entries) as $id) {
            $ids[] = $id;
        }

        if (! empty($ids)) {
            Redis::xack($stream, $group, $ids);
        }

        // Borne la mémoire Redis (les entrées ackées n'ont plus besoin d'être gardées).
        try {
            Redis::xtrim($stream, 100000, true);
        } catch (\Throwable $e) {
            // non bloquant
        }

        Log::info('[ARCHIVE] ' . count($ids) . ' fights archived from stream.');
    }

    private function ensureGroup(string $stream, string $group): void
    {
        try {
            Redis::xgroup('CREATE', $stream, $group, '0', true);
        } catch (\Throwable $e) {
            // BUSYGROUP = déjà créé, on ignore.
        }
    }

    private function appendArchive(string $dir, array $entries): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = rtrim($dir, '/') . '/' . now()->format('Ymd') . '.jsonl';

        $lines = '';
        foreach ($entries as $id => $fields) {
            $lines .= json_encode(['id' => $id, 'ts' => now()->toIso8601String(), 'fight' => $fields]) . PHP_EOL;
        }

        if ($lines !== '') {
            @file_put_contents($file, $lines, FILE_APPEND | LOCK_EX);
        }
    }
}
