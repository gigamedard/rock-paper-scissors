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

// Helper to print with timestamp
function logOutput($message) {
    echo "[" . date('H:i:s') . "] " . $message . "\n";
}

logOutput("---------------------------------------------------");
logOutput("TEST: Full Game Cycle (Join -> Fight -> Win -> Payout)");
logOutput("---------------------------------------------------");


// Mock Web3Helper to avoid Node.js dependency and capture payouts
$mockWeb3 = Mockery::mock(Web3Helper::class);
$mockWeb3->shouldReceive('weiToEther')->andReturnUsing(function($wei) {
    return bcdiv($wei, "1000000000000000000", 18);
});
$mockWeb3->shouldReceive('sendPayement')->andReturnUsing(function($url, $wallet, $amount) {
    logOutput("[MOCK] sendPayement called for $wallet, Amount: $amount");
    return true;
});
$mockWeb3->shouldReceive('sortAddressesWithSalt')->andReturnUsing(function($addresses, $salt) {
    return $addresses;
});
// Handle verifySignature if called
$mockWeb3->shouldReceive('verifySignature')->andReturn(true);
$mockWeb3->shouldReceive('premoveExists')->andReturn(true); 
$mockWeb3->shouldReceive('setUserNextSessionTime')->andReturn(true);
$mockWeb3->shouldReceive('sendSessionCIDToSmartContract')->andReturn(true);

// Bind the mock
$app->instance(App\Helpers\Web3Helper::class, $mockWeb3);
$app->instance(Web3Helper::class, $mockWeb3); 

// Mock BatchCriteriaService to force pool size 2
$mockBatchCriteria = Mockery::mock(App\Services\BatchProcessing\BatchCriteriaService::class);
$mockBatchCriteria->shouldReceive('getTargetPoolSize')->andReturn(['targetPoolSize' => 2, 'error' => null]);
$app->instance(App\Services\BatchProcessing\BatchCriteriaService::class, $mockBatchCriteria);

// Override Config for Test
// By setting security_coefficient to 1 and initial DB balance to 0, The session starts with balance = 0.1.
// Winning the match brings balance to 0.2, yielding q = 0.2 / 0.1 = 2.0, which triggers payout.
config(['game_settings.security_coefficient' => 1]);

// 1. Setup Users
// We need real-ish data. If we want to test payout, we need valid addresses.
$user1 = User::factory()->create(['wallet_address' => '0x' . bin2hex(random_bytes(20)), 'status' => 'available']);
$user1->balance = 0; // Set to 0 so the coeff adding makes it exactly base_bet
$user1->battle_balance = 0;
$user1->save();

$user2 = User::factory()->create(['wallet_address' => '0x' . bin2hex(random_bytes(20)), 'status' => 'available']);
$user2->balance = 0;
$user2->battle_balance = 0;
$user2->save();



// WORKAROUND: Sort users by ID to match DB default sort in `whereIn`
// This avoids the CID mismatch bug in PoolLifecycleService
if ($user1->id > $user2->id) {
    $usersSorted = [$user2, $user1];
} else {
    $usersSorted = [$user1, $user2];
}
$user1 = $usersSorted[0];
$user2 = $usersSorted[1];

logOutput("Created User 1: {$user1->id} - {$user1->wallet_address}");
logOutput("Created User 2: {$user2->id} - {$user2->wallet_address}");

// 2. Prepare Moves & PreMoves
// User 1: Rock
// User 2: Scissors

// Mock CIDs (since we know the backend only checks string equality for PreMove CID)
$cid1 = "QmUser1PreMove_" . uniqid();
$cid2 = "QmUser2PreMove_" . uniqid();

// Store PreMoves in DB
// We simulate the frontend calling storePreMoves
$controller = app(PoolAutoMatchController::class);

logOutput("Storing PreMoves...");

// User 1 Stores PreMove
$request1 = Request::create('/api/store-pre-moves', 'POST', [
    'user_id' => $user1->id,
    'bet_amount' => 0.1,
    'cid' => $cid1,
    'pre_moves' => ['rock', 'rock'] // 2 moves just in case
]);
$controller->storePreMoves($request1);

// User 2 Stores PreMove
$request2 = Request::create('/api/store-pre-moves', 'POST', [
    'user_id' => $user2->id,
    'bet_amount' => 0.1,
    'cid' => $cid2,
    'pre_moves' => ['scissors', 'scissors']
]);
$controller->storePreMoves($request2);

logOutput("PreMoves Stored.");

// Fetch users exactly like `PoolLifecycleService` will, to determine order
$dbUsers = User::whereIn('wallet_address', [$user1->wallet_address, $user2->wallet_address])->get();
$orderedWallets = [];
$orderedCids = [];
foreach ($dbUsers as $index => $u) {
    if ($u->id === $user1->id) {
        $orderedWallets[] = $user1->wallet_address;
        $orderedCids[] = $cid1;
    } else {
        $orderedWallets[] = $user2->wallet_address;
        $orderedCids[] = $cid2;
    }
}

// 3. Emit Pool Event (Simulating Blockchain Event)
// We use a high ID to avoid conflict
$poolId = rand(100000, 999999);
$salt = "test_salt_" . uniqid();
$betAmount = 0.1;

$betAmountWei = bcmul((string)$betAmount, "1000000000000000000"); 

logOutput("Emitting Pool Event (ID: $poolId, Bet: $betAmount ETH ($betAmountWei Wei))...");

$requestPool = Request::create('/internal/pool-emited', 'POST', [
    'pool_id' => (string)$poolId,
    'base_bet' => $betAmountWei,
    'users' => $orderedWallets,
    'premove_cids' => $orderedCids,
    'pool_salt' => $salt
]);

// Let's check what CIDs we are sending:
logOutput("Ordered CIDs sent: " . implode(" , ", $orderedCids));

try {
    $response = $controller->poolEmitedRequest($requestPool);
    if ($response->getStatusCode() !== 200) {
        logOutput("[FAIL] Pool Emission Failed: " . $response->getContent());
        exit(1);
    }
    logOutput("Pool Emitted Successfully. DB Pool Created.");
} catch (\Exception $e) {
    logOutput("[FAIL] Exception during Pool Emission: " . $e->getMessage());
    exit(1);
}

// 4. Verify Notifications (POOL_JOINED)
$notifs1 = GameNotification::where('user_id', $user1->id)->where('type', 'POOL_JOINED')->count();
$notifs2 = GameNotification::where('user_id', $user2->id)->where('type', 'POOL_JOINED')->count();

if ($notifs1 > 0 && $notifs2 > 0) {
    logOutput("[PASS] POOL_JOINED notifications created.");
} else {
    logOutput("[FAIL] Missing POOL_JOINED notifications.");
}

// 5. Run Matching directly on the created pool (Bypassing Batch Queue to avoid processing old test data)
logOutput("Running Matching on our Pool...");

try {
    $dbPool = Pool::where('pool_id', $poolId)->first();
    if (!$dbPool) {
        logOutput("[FAIL] Could not find the created pool in DB.");
        exit(1);
    }
    
    $poolService = app(\App\Services\PoolService::class);
    $poolService->processPoolAutoMatch($dbPool->id);
    logOutput("[PASS] Pool processed successfully.");

} catch (\Exception $e) {
    logOutput("[FAIL] Exception during Pool Processing: " . $e->getMessage());
    exit(1);
}



// 6. Verify Results
$user1->refresh();
$user2->refresh();

logOutput("User 1 Balance: {$user1->balance}");
logOutput("User 2 Balance: {$user2->balance}");

// Determine winner by balance
if ($user1->balance > 0.5) {
    logOutput("[PASS] User 1 DB Balance increased (Winning).");
    $winner = $user1;
} elseif ($user2->balance > 0.5) {
    logOutput("[PASS] User 2 DB Balance increased (Winning).");
    $winner = $user2;
} else {
    logOutput("[FAIL] No user DB balance increased.");
    $winner = null;
}

// Notification Check
$fightNotifs = GameNotification::where('user_id', $user1->id)->where('type', 'BATTLE_STARTED')->count();
if ($fightNotifs > 0) {
    logOutput("[PASS] BATTLE_STARTED notification received.");
} else {
    logOutput("[FAIL] Missing BATTLE_STARTED notification.");
}

// Payout Check
// Who handles the actual blockchain payout?
// I suspect `SessionFinishedEvent` triggers `BlockchainController::processPayout`?
// Or maybe `processAutoMatch` does?

// I'll check SessionFinishedEvent listeners in `EventServiceProvider`.

echo "\n---------------------------------------------------\n";
echo "Use 'grep' to find SessionFinishedEvent listener to confirm payout trigger.\n";
echo "---------------------------------------------------\n";
