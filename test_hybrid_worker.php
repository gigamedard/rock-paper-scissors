<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "--- START HYBRID WORKER VERIFICATION ---\n";

// 1. Setup Data - Create 2 available users with bet amount matching the worker default
$baseBet = 0.01; // Matches BASE_BET in run_batch_processor.js
$users = User::factory()->count(5)->create([
    'balance' => 10, 'battle_balance' => 0, 'status' => 'available', 
    'bet_amount' => $baseBet, 'autoplay_active' => true
]);

// Give them unique wallets to avoid constraint errors if factory doesn't
foreach ($users as $index => $u) {
    $u->wallet_address = '0xWorkerTest_' . $index . '_' . Str::random(5);
    $u->save();
}

// PreMoves needed for fights
foreach ($users as $u) {
    DB::table('pre_moves')->insert([
        ['user_id' => $u->id, 'moves' => json_encode(['rock']), 'current_index' => 0]
    ]);
}

echo "Created " . count($users) . " Users with status 'available'.\n";

// 2. Trigger Hybrid Worker loop
$workerUrl = 'http://localhost:3001/trigger-sync';
$maxCycles = 5;

echo "Triggering Worker at $workerUrl ($maxCycles cycles)...\n";

for ($i = 0; $i < $maxCycles; $i++) {
    echo "  Cycle " . ($i+1) . "... ";
    try {
        $ch = curl_init($workerUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "Response: $httpCode\n";
    } catch (Exception $e) {
        echo "Failed to contact worker: " . $e->getMessage() . "\n";
    }
    sleep(1); // Small delay between triggers
}

// 3. Verify Final State
$inPoolOrStopped = User::whereIn('id', $users->pluck('id'))
    ->where(function($q) {
        $q->where('status', 'in_pool')->orWhere('status', 'stopped');
    })->count();

$available = User::whereIn('id', $users->pluck('id'))->where('status', 'available')->count();

echo "\n--- VERIFICATION RESULTS ---\n";
echo "Users in_pool/stopped: $inPoolOrStopped\n";
echo "Users available: $available\n";

$fights = Fight::whereIn('user1_id', $users->pluck('id'))->count();
echo "Total Fights handled: $fights\n";

if ($inPoolOrStopped > 0 || $fights > 0) {
    echo "✅ SUCCESS: Fights occurred. Recycling active.\n";
} else {
    echo "❌ FAILURE: No fights occurred or users stuck.\n";
}

// Cleanup
User::whereIn('id', $users->pluck('id'))->delete();
DB::table('pre_moves')->whereIn('user_id', $users->pluck('id'))->delete();
Pool::where('base_bet', $baseBet)->delete();
