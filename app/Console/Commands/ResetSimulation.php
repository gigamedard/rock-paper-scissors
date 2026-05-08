<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class ResetSimulation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulation:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset simulation data (bet_amount, fights, pools, batches) for E2E testing.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting simulation reset...');

        // 1. Reset Users
        $updated = User::query()->update([
            'status' => 'available',
            'pool_id' => null,
            'bet_amount' => 0.01, // Default Martingale base
            'balance' => 10.00,   // Restore initial capital
            'session_started' => false,
            'session_start_balance' => 0.00,
            'session_start_battle_balance' => 0.00,
            'battle_balance' => 0.00
        ]);
        $this->info("Reset session fields and restored 10 ETH balance for {$updated} users.");


            // 2. Truncate Tables
            // Disable foreign key checks temporarily if needed, though truncating might work directly if ordered correctly
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            DB::table('fights')->truncate();
            $this->info('Truncated fights table.');

            DB::table('game_notifications')->truncate();
            $this->info('Truncated game_notifications table.');

            DB::table('pools')->truncate();
            $this->info('Truncated pools table.');

            DB::table('batches')->truncate();
            $this->info('Truncated batches table.');

            // Agent 3 (QA) additions: prevent queue pollution and stat corruption
            DB::table('jobs')->truncate();
            DB::table('failed_jobs')->truncate();
            $this->info('Truncated jobs and failed_jobs tables (Queue flushed).');

            if (Schema::hasTable('match_histories')) {
                DB::table('match_histories')->truncate();
            }
            if (Schema::hasTable('f_hists')) {
                DB::table('f_hists')->truncate();
            }
            $this->info('Truncated match histories.');

            // Reset pre-moves session trackers to prevent cross-session pollution
            if (Schema::hasTable('pre_moves')) {
                DB::table('pre_moves')->truncate();
                $this->info('Truncated pre_moves table.');
            }


            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Simulation reset completed successfully!');
    }
}
