<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WatchdogCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if the Node.js Bridge is still sending pings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lastPing = Cache::get('bridge_last_ping');

        if (!$lastPing) {
            Log::error('CRITICAL: Node.js Bridge is down! No ping received yet.');
            $this->error('Node.js Bridge is down!');
            return 1;
        }

        $diff = now()->timestamp - $lastPing;

        if ($diff > 10) {
            Log::error("CRITICAL: Node.js Bridge is down! Last ping was {$diff} seconds ago.");
            $this->error("Node.js Bridge is down! Last ping was {$diff} seconds ago.");
            return 1;
        }

        $this->info("Node.js Bridge is healthy. Last ping was {$diff} seconds ago.");
        return 0;
    }
}
