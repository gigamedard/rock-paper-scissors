<?php

namespace App\Console\Commands;

use App\Services\SideEffectOutbox;
use Illuminate\Console\Command;

/**
 * Consumer de l'outbox : exécute les effets de bord humains (payout, notifs,
 * signatures, syncLimits, cooldown, broadcasts) hors de la boucle de jeu.
 */
class ProcessSideEffectOutbox extends Command
{
    protected $signature = 'outbox:process
        {--once : Un seul lot puis quitte}
        {--limit=200 : Taille du lot}
        {--sleep=1 : Secondes entre deux lots (mode boucle)}';

    protected $description = 'Traite l\'outbox des effets de bord (payouts/notifs humains)';

    public function handle(SideEffectOutbox $outbox): int
    {
        $once = (bool) $this->option('once');
        $limit = max(1, (int) $this->option('limit'));
        $sleep = max(0, (int) $this->option('sleep'));

        do {
            $r = $outbox->processBatch($limit);
            if ($r['processed'] > 0) {
                $this->info(sprintf(
                    '[OUTBOX] processed=%d done=%d failed=%d',
                    $r['processed'], $r['done'], $r['failed']
                ));
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
