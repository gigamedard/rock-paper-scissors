<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;

echo "--- STARTING FULL DATABASE RESET ---\n";

try {
    echo "Truncating Pools...\n";
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    Pool::truncate();
    echo "Truncating Fights...\n";
    Fight::truncate();
    echo "Truncating FHist (if exists)...\n";
    DB::table('f_hists')->truncate();
    echo "Truncating Batches...\n";
    DB::table('batches')->truncate();
    echo "Truncating Influencer Fees...\n";
    DB::table('influencer_fees')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    echo "Resetting All Users...\n";
    User::query()->update([
        'status' => 'stopped', // Start as stopped, simulation will start them
        'session_started' => false,
        'autoplay_active' => false,
        'balance' => 0, // Will be synced from blockchain
        'battle_balance' => 0,
        'session_start_balance' => 0,
        'session_start_battle_balance' => 0,
        'payout_signature' => null,
        'pool_id' => null
    ]);

    echo "Clearing Cache...\n";
    \Illuminate\Support\Facades\Cache::flush();

    echo "--- RESET COMPLETED SUCCESSFULLY ---\n";
} catch (\Exception $e) {
    echo "ERROR DURING RESET: " . $e->getMessage() . "\n";
}
