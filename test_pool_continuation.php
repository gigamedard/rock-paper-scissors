<?php

use App\Models\User;
use App\Models\Pool;
use App\Models\GameNotification;
use App\Http\Controllers\PoolAutoMatchController;
use App\Services\BatchProcessingService;
use App\Helpers\Web3Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function logOutput($message) {
    echo "[" . date('H:i:s') . "] " . $message . "\n";
}

logOutput("---------------------------------------------------");
logOutput("TEST: Pool Continuation (Win but no Payout)");
logOutput("---------------------------------------------------");

// Mock Web3Helper
$mockWeb3 = Mockery::mock(Web3Helper::class);
$mockWeb3->shouldReceive('weiToEther')->andReturnUsing(function($wei) { return bcdiv($wei, "1000000000000000000", 18); });
$mockWeb3->shouldReceive('sendPayement')->andReturnUsing(function($url, $wallet, $amount) {
    logOutput("[MOCK] sendPayement called. THIS SHOULD NOT HAPPEN IN THIS TEST!");
    return true;
});
$mockWeb3->shouldReceive('sortAddressesWithSalt')->andReturnUsing(function($addresses, $salt) { return $addresses; });
$mockWeb3->shouldReceive('verifySignature')->andReturn(true);
$mockWeb3->shouldReceive('premoveExists')->andReturn(true); 
$mockWeb3->shouldReceive('setUserNextSessionTime')->andReturn(true);
$mockWeb3->shouldReceive('sendSessionCIDToSmartContract')->andReturn(true);

$app->instance(App\Helpers\Web3Helper::class, $mockWeb3);
$app->instance(Web3Helper::class, $mockWeb3); 

$mockBatchCriteria = Mockery::mock(App\Services\BatchProcessing\BatchCriteriaService::class);
$mockBatchCriteria->shouldReceive('getTargetPoolSize')->andReturn(['targetPoolSize' => 2, 'error' => null]);
$app->instance(App\Services\BatchProcessing\BatchCriteriaService::class, $mockBatchCriteria);

// To NOT reach the win limit, we need initial > bet.
// Let's set initial = 0.05. Bet = 0.01.
// Winning makes it 0.06. q = 0.06 / 0.05 = 1.2 (which is < 2.0).
config(['game_settings.security_coefficient' => 1]);

$user1 = User::factory()->create(['wallet_address' => '0x' . bin2hex(random_bytes(20)), 'status' => 'available']);
$user1->balance = 0.04; // 0.04 + (0.01 coeff) = 0.05 initial
$user1->battle_balance = 0;
$user1->save();

$user2 = User::factory()->create(['wallet_address' => '0x' . bin2hex(random_bytes(20)), 'status' => 'available']);
$user2->balance = 0.04;
$user2->battle_balance = 0;
$user2->save();

$user3 = User::factory()->create(['wallet_address' => '0x' . bin2hex(random_bytes(20)), 'status' => 'available']);
$user3->balance = 0.04;
$user3->battle_balance = 0;
$user3->save();

if ($user1->id > $user2->id) {
    $usersSorted = [$user2, $user1];
} else {
    $usersSorted = [$user1, $user2];
}
$user1 = $usersSorted[0];
$user2 = $usersSorted[1];

logOutput("Created User 1: {$user1->id}");
logOutput("Created User 2: {$user2->id}");
logOutput("Created User 3: {$user3->id}");

$cid1 = "QmUser1PreMove_" . uniqid();
$cid2 = "QmUser2PreMove_" . uniqid();
$cid3 = "QmUser3PreMove_" . uniqid();

$controller = app(PoolAutoMatchController::class);

$request1 = Request::create('/api/store-pre-moves', 'POST', ['user_id' => $user1->id, 'bet_amount' => 0.01, 'cid' => $cid1, 'pre_moves' => ['rock', 'rock']]);
$controller->storePreMoves($request1);

$request2 = Request::create('/api/store-pre-moves', 'POST', ['user_id' => $user2->id, 'bet_amount' => 0.01, 'cid' => $cid2, 'pre_moves' => ['scissors', 'scissors']]);
$controller->storePreMoves($request2);

$request3 = Request::create('/api/store-pre-moves', 'POST', ['user_id' => $user3->id, 'bet_amount' => 0.01, 'cid' => $cid3, 'pre_moves' => ['rock', 'rock']]);
$controller->storePreMoves($request3);

$dbUsers = User::whereIn('wallet_address', [$user1->wallet_address, $user2->wallet_address])->get();
$orderedWallets = [];
$orderedCids = [];
foreach ($dbUsers as $u) {
    if ($u->id === $user1->id) { $orderedWallets[] = $user1->wallet_address; $orderedCids[] = $cid1; } 
    else { $orderedWallets[] = $user2->wallet_address; $orderedCids[] = $cid2; }
}

$poolId = rand(100000, 999999);
$salt = "test_salt_" . uniqid();
$betAmount = 0.01;
$betAmountWei = bcmul((string)$betAmount, "1000000000000000000"); 

$requestPool = Request::create('/internal/pool-emited', 'POST', [
    'pool_id' => (string)$poolId,
    'base_bet' => $betAmountWei,
    'users' => $orderedWallets,
    'premove_cids' => $orderedCids,
    'pool_salt' => $salt,
    'balances' => ['1000000000000000000', '1000000000000000000']
]);

$controller->poolEmitedRequest($requestPool);

logOutput("Running Matching on our Pool ($poolId)...");
$dbPool = Pool::where('pool_id', $poolId)->first();
$poolService = app(\App\Services\PoolService::class);
$poolService->processPoolAutoMatch($dbPool->id);

logOutput("Running Auto Matchmaking for recycled users...");
config(['pool.size' => [2]]);
$internalPoolService = app(\App\Services\InternalPoolService::class);
$matchResult = $internalPoolService->processInternalPools(0.01);
print_r($matchResult);

$user1->refresh();
$user2->refresh();
$user3->refresh();

logOutput("User 1 Post-Match Balance: {$user1->balance}, Status: {$user1->status}, Pool ID: {$user1->pool_id}");
logOutput("User 2 Post-Match Balance: {$user2->balance}, Status: {$user2->status}, Pool ID: {$user2->pool_id}");
logOutput("User 3 Post-Match Balance: {$user3->balance}, Status: {$user3->status}, Pool ID: {$user3->pool_id}");

// Verify Continuation
// User 1 won rock vs scissors. User 1's q = 1.2. 
// User 1 should NOT be 'available'. They should be 'in_pool' assigned to a new Pool ID that is different from $dbPool->id.
if ($user1->balance > 0.04) {
    logOutput("[PASS] User 1 DB Balance increased (Winning).");
    if ($user1->status === 'in_pool' && $user1->pool_id !== null && $user1->pool_id !== $dbPool->id) {
        logOutput("[PASS] User 1 assigned to a new internal Pool ID: {$user1->pool_id}");
        
        $newPool = Pool::find($user1->pool_id);
        logOutput("   -> New Pool Base Bet: {$newPool->base_bet}, Status: {$newPool->status}");
    } else {
        logOutput("[FAIL] User 1 was not correctly assigned to a new pool.");
    }
} else {
    logOutput("[FAIL] User 1 did not win or balance calculation failed.");
}

// User 2 lost. Balance dropped. 
if ($user2->balance < 0.04) {
    // If they have enough to play again (balance > 0.02 because bet doubled)
    logOutput("User 2 Bet Amount is now: {$user2->bet_amount}");
    if ($user2->status === 'in_pool') {
         logOutput("[PASS] User 2 remains in_pool (Waiting for next pool with doubled bet).");
    } elseif ($user2->status === 'stopped') {
        logOutput("[PASS/INFO] User 2 stopped (Insufficient Funds for doubled bet).");
    }
}
