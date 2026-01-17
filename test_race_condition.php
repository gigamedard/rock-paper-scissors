<?php

use App\Models\User;
use App\Models\Pool;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- START CONCURRENCY / RACE CONDITION TEST ---\n";

// 1. Setup: Create enough users for ONE pool (e.g. 5 users)
// If we run 2 concurrent processes, BOTH might grab these 5 users if not locked.
$baseBet = 0.01;
Pool::where('base_bet', $baseBet)->delete();
User::where('wallet_address', 'like', '0xRace_%')->delete();

$users = User::factory()->count(5)->create([
    'balance' => 10, 
    'battle_balance' => 0, 
    'status' => 'available', 
    'bet_amount' => $baseBet, 
    'autoplay_active' => true
]);

foreach ($users as $index => $u) {
    $u->wallet_address = '0xRace_' . $index . '_' . Str::random(5);
    $u->save();
}

echo "Created 5 users (Target Pool Size = 5). Correct behavior: Only 1 pool created. Race failure: 2 pools created sharing users.\n";

// 2. Execute Concurrent Requests
// We will use curl_multi to fire two requests to the Internal Pools endpoint simultaneously.
$url = 'http://127.0.0.1:8000/api/internal/internal-pools';
$apiSecret = env('INTERNAL_API_SECRET', 'my_secure_internal_secret'); // Adjust if needed

$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < 2; $i++) {
    $ch = curl_init($url);
    $payload = json_encode(['base_bet' => $baseBet]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        "X-Internal-Secret: $apiSecret"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

echo "Firing 2 concurrent requests...\n";

$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running);

foreach ($handles as $ch) {
    echo "Response Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

// 3. Analyze Results
$pools = Pool::where('base_bet', $baseBet)->get();
echo "Total Pools Created: " . $pools->count() . "\n";

if ($pools->count() > 1) {
    echo "❌ CRITICAL FAILURE: Race Condition Detected!\n";
    echo "   Rationale: 5 users were split into " . $pools->count() . " pools (Impossible without cloning).\n";
    echo "   Users are now 'ghosts' in one of the pools.\n";
} elseif ($pools->count() === 1) {
    echo "✅ SUCCESS: Locking held (or race didn't trigger). Only 1 pool created.\n";
} else {
    echo "⚠️ FAILURE: No pools created? Check logs.\n";
}

// Cleanup
// User::where('wallet_address', 'like', '0xRace_%')->delete();
