<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Pool;
use App\Services\InternalPoolService;
use Illuminate\Support\Facades\DB;

echo "--- START SCENARIO B: InternalPoolService Verification ---\n";

// 1. Setup Data - Create 5 available users
$baseBet = 0.05; // Unique bet amount to avoid clashing with other tests
$users = User::factory()->count(5)->create([
    'balance' => 10, 
    'status' => 'available', 
    'bet_amount' => $baseBet,
    'autoplay_active' => true
]);

foreach ($users as $u) {
    DB::table('pre_moves')->insert([
        ['user_id' => $u->id, 'moves' => json_encode(['rock']), 'current_index' => 0]
    ]);
}

echo "Created 5 users with status 'available' and bet_amount {$baseBet}.\n";

// 2. Call InternalPoolService
$service = app(InternalPoolService::class);
echo "Calling processInternalPools($baseBet)...\n";
$result = $service->processInternalPools($baseBet);

print_r($result);

// 3. Verify Results
$inPoolCount = User::whereIn('id', $users->pluck('id'))->where('status', 'in_pool')->count();
echo "Users moved to 'in_pool': $inPoolCount / 5\n";

if ($inPoolCount === 5) {
    echo "✅ TEST PASSED: All users recycled into a pool.\n";
} else {
    echo "❌ TEST FAILED: Only $inPoolCount users recycled.\n";
}

// Cleanup
User::whereIn('id', $users->pluck('id'))->delete();
DB::table('pre_moves')->whereIn('user_id', $users->pluck('id'))->delete();
Pool::where('base_bet', $baseBet)->delete();
