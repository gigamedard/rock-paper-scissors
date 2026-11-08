<?php

namespace App\Console\Commands;

use App\Services\RefundQueueService;
use Illuminate\Console\Command;

/**
 * Paie par lots FIFO les remboursements dus, tant que le fonds le permet.
 * À planifier (ex. chaque minute) dans le scheduler.
 */
class ProcessRefundQueue extends Command
{
    protected $signature = 'refund:process
        {--once : Exécute un seul batch puis quitte}
        {--sleep=30 : Secondes entre deux batches (mode boucle)}';

    protected $description = 'Fonds de recouvrement : paie les premiers de la file FIFO par lots';

    public function handle(RefundQueueService $service): int
    {
        $once = (bool) $this->option('once');
        $sleep = max(1, (int) $this->option('sleep'));

        do {
            $r = $service->processBatch();

            $this->info(sprintf(
                '[REFUND] waiting=%d dette=%.4f fonds_avant=%.4f payé=%.4f (%d) fonds_après=%.4f déclenché=%s',
                $r['waiting'],
                $r['queue_total'],
                $r['fund_before'],
                $r['paid'],
                $r['paid_count'],
                $r['fund_after'],
                $r['triggered'] ? 'oui' : 'non'
            ));

            if ($once) {
                break;
            }

            sleep($sleep);
        } while (true);

        return self::SUCCESS;
    }
}
