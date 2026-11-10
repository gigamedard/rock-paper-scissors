<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveFightStream;
use App\Services\InMemorySimulationService;
use Illuminate\Console\Command;

/**
 * Full in-memory : joue la cohorte P3 sans écriture DB par pool.
 * Audit via Redis Stream ; persistance uniquement aux fins de session.
 */
class RunMemorySimulation extends Command
{
    protected $signature = 'sim:memory
        {--once : Un seul cycle puis quitte}
        {--pools=60 : Pools max par cycle}
        {--limit=1000 : Taille max de la cohorte chargée}
        {--sleep=0 : Secondes entre deux cycles}';

    protected $description = 'Simulation full in-memory (bots P3, audit Redis Stream)';

    public function handle(InMemorySimulationService $sim): int
    {
        $n = $sim->loadCohort((int) $this->option('limit'));
        $this->info("[SIM] cohort chargée = {$n} bots");

        $once = (bool) $this->option('once');
        $pools = (int) $this->option('pools');
        $sleep = max(0.0, (float) $this->option('sleep'));

        do {
            $r = $sim->runCycle($pools);

            for ($i = 0; $i < 4; $i++) {
                try {
                    (new ArchiveFightStream())->handle();
                } catch (\Throwable $e) {
                    break;
                }
            }

            if ($r['pools'] > 0) {
                $fps = $r['seconds'] > 0 ? $r['fights'] / $r['seconds'] : 0;
                $this->info(sprintf(
                    '[SIM] pools=%d fights=%d wins=%d fails=%d %.2fs (%.0f fights/s, %.0f pools/s)',
                    $r['pools'], $r['fights'], $r['wins'], $r['fails'], $r['seconds'], $fps,
                    $r['seconds'] > 0 ? $r['pools'] / $r['seconds'] : 0
                ));
            }

            if ($once) {
                break;
            }

            if ($sleep > 0) {
                usleep((int) ($sleep * 1_000_000));
            }
        } while (true);

        return self::SUCCESS;
    }
}
