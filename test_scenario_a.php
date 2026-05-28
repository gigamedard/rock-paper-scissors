<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Fight;
use App\Models\Pool;
use App\Services\FightService;
use App\Events\SessionFinishedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

echo "--- START SCENARIO A: Recycling Failure Verification ---\n";

// 1. Setup Data
$baseBet = 0.01;
$user1 = User::factory()->create([
    'balance' => 10,
    'battle_balance' => 1.0,
    'session_start_battle_balance' => 1.0,
    'session_start_balance' => 10,
    'session_started' => true,
    'status' => 'in_pool',
    'bet_amount' => $baseBet,
    'autoplay_active' => true,
    'wallet_address' => '0xUser1_' . \Illuminate\Support\Str::random(5)
]);
$user2 = User::factory()->create([
    'balance' => 10,
    'battle_balance' => 1.0,
    'session_start_battle_balance' => 1.0,
    'session_start_balance' => 10,
    'session_started' => true,
    'status' => 'in_pool',
    'bet_amount' => $baseBet,
    'autoplay_active' => true,
    'wallet_address' => '0xUser2_' . \Illuminate\Support\Str::random(5)
]);

// Ensure PreMove exists (required for logic)
DB::table('pre_moves')->insert([
    ['user_id' => $user1->id, 'moves' => json_encode(['rock']), 'current_index' => 0],
    ['user_id' => $user2->id, 'moves' => json_encode(['paper']), 'current_index' => 0]
]);

$pool = Pool::create(['base_bet' => $baseBet, 'pool_size' => 2, 'salt' => \Illuminate\Support\Str::random(10), 'status' => 'from_server_waitting']);

$fight = Fight::create([
    'user1_id' => $user1->id,
    'user2_id' => $user2->id,
    'base_bet_amount' => $baseBet,
    'pool_id' => $pool->id,
    'status' => 'waiting_for_result'
]);

echo "Created Fight ID: {$fight->id} between U1 ({$user1->id}) and U2 ({$user2->id})\n";

// 2. Execute Fight (User 1 Rock vs User 2 Paper -> User 2 Wins)
$fightService = app(FightService::class);
$fightService->handlePoolAutoplayFight($fight, $baseBet, 2);

$sessionManager = app(\App\Services\SessionManager::class);
$sessionManager->evaluatePoolEnd($pool);

$user1->refresh();
echo "User 1 (Loser) Status after fight: {$user1->status} (Expected: available)\n";
echo "User 1 Pool ID: " . ($user1->pool_id ?? 'NULL') . "\n";

if ($user1->status !== 'available') {
    echo "❌ TEST FAILED: User 1 should be 'available'.\n";
    exit(1);
}

$user1->refresh();
if ($user1->status === 'available') {
    echo "✅ TEST PASSED: User 1 remained 'available'.\n";
} else {
    echo "❌ TEST FAILED: User 1 status is {$user1->status}.\n";
}

// Cleanup
$user1->delete();
$user2->delete();
$pool->delete();
DB::table('pre_moves')->whereIn('user_id', [$user1->id, $user2->id])->delete();
