<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveFightStream;
use App\Services\GameEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Boucle persistante du moteur de jeu (Swoole-compatible).
 *
 * Chaque tick : traite les pools en attente en mémoire, flush les events vers
 * le front (batch), puis déclenche le consumer d'audit si besoin.
 * Le lock empêche deux moteurs concurrents.
 */
class RunGameEngine extends Command
{
    protected $signature = 'game:engine
        {--once : Exécute un seul tick puis quitte}
        {--sleep= : Intervalle entre deux ticks en secondes}';

    protected $description = 'Moteur de jeu en mémoire (pools + Redis Stream + events batch)';

    public function handle(GameEngineService $engine): int
    {
        $lock = Cache::lock('game_engine_tick', 30);
        if (! $lock->get()) {
            $this->warn('Un autre moteur détient le lock, abandon.');

            return self::SUCCESS;
        }

        try {
            $once = (bool) $this->option('once');
            $sleep = $this->option('sleep') !== null
                ? (float) $this->option('sleep')
                : (float) config('game_engine.tick_seconds', 1.5);

            do {
                $start = microtime(true);

                $result = $engine->tick();
                if ($result['pools'] > 0) {
                    $this->info(sprintf(
                        '[ENGINE] %d pools traitées, %d lots d\'events envoyés (%.1f ms)',
                        $result['pools'],
                        $result['event_batches'],
                        (microtime(true) - $start) * 1000
                    ));
                }

                // Consumer d'audit (plusieurs batches/tick pour rattraper le Stream)
                $batches = max(1, (int) config('game_engine.archive_batches_per_tick', 4));
                for ($i = 0; $i < $batches; $i++) {
                    try {
                        (new ArchiveFightStream())->handle();
                    } catch (\Throwable $e) {
                        $this->warn('[ARCHIVE] ' . $e->getMessage());
                        break;
                    }
                }

                if ($once) {
                    break;
                }

                usleep((int) ($sleep * 1_000_000));
            } while (true);
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
