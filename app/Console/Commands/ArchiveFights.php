<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveFightStream;
use Illuminate\Console\Command;

/**
 * Consumer d'audit dédié : consomme le Redis Stream des combats et les archive
 * (JSONL) de façon autonome, même si aucun moteur ne tourne. À planifier.
 */
class ArchiveFights extends Command
{
    protected $signature = 'fights:archive
        {--once : Un seul batch puis quitte}
        {--sleep=2 : Secondes entre deux batches}';

    protected $description = 'Consomme le Redis Stream des combats (audit/équité)';

    public function handle(): int
    {
        $once = (bool) $this->option('once');
        $sleep = max(0, (int) $this->option('sleep'));

        do {
            try {
                (new ArchiveFightStream())->handle();
            } catch (\Throwable $e) {
                $this->warn('[ARCHIVE] ' . $e->getMessage());
            }

            if ($once) {
                break;
            }
            if ($sleep > 0) {
                sleep($sleep);
            }
        } while (true);

        return self::SUCCESS;
    }
}
