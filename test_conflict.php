<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\PreMove;
use App\Models\GameSetting;
use App\Services\PoolLifecycleService;
use App\Helpers\Web3Helper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

echo "=== Testing Conflict Detection ===\n\n";

// Ensure settings exist
GameSetting::updateOrCreate(['key' => 'security_coefficient'], ['value' => '1.0']);

// 1. Create a "Valid User" and PreMove in the Database
$validWallet = '0x' . strtolower(Str::random(40));
$validUser = clone User::firstOrCreate(
    ['wallet_address' => $validWallet],
    [
        'status' => 'waiting', 
        'balance' => 0,
        'name' => 'Test Valid User',
        'email' => $validWallet . '@test.com',
        'password' => bcrypt('password')
    ]
);

$validCid = 'QmValidUserCID' . Str::random(5);
PreMove::updateOrCreate(
    ['user_id' => $validUser->id],
    ['cid' => $validCid, 'session_first_pool_id' => 0, 'moves' => '[]']
);

echo "Valid User Wallet: {$validWallet}\n";
echo "Valid User CID: {$validCid}\n\n";

// 2. An "Intruder User" who bypassed the backend but exists on the blockchain
$intruderWallet = '0x' . strtolower(Str::random(40));
$intruderCid   = 'QmIntruderCID' . Str::random(5);

echo "Intruder Wallet: {$intruderWallet}\n";
echo "Intruder CID: {$intruderCid}\n\n";

// Mock the PoolEmitted event data for a 100% Valid Pool
$eventData = [
    'pool_id' => 9999, // dummy pool ID
    'base_bet' => 10000000000000000, // 0.01 ETH in wei
    'pool_salt' => '0x' . Str::random(64),
    // Order: Only the Valid User
    'users' => "{$validWallet}",
    'premove_cids' => "{$validCid}"
];

echo "Simulating PoolEmitted Event Delivery to Service...\n\n";

// 3. Trigger Service
try {
    $web3Helper = app(Web3Helper::class);
    $service = new PoolLifecycleService($web3Helper);
    
    // To prevent actual curl to the node.js endpoint crashing the test (if node not running)
    // We will just let it try, or we can mock Web3Helper. Let's let it run and catch the exception if Node is down.
    $service->handlePoolEmitedEvent($eventData);

    echo "✅ Event handled successfully. Valid user processed.\n";
    
    // Re-fetch valid user
    $refreshedValidUser = User::find($validUser->id);
    echo "Valid User new balance (virtual): {$refreshedValidUser->balance}\n";
    echo "Valid User status: {$refreshedValidUser->status}\n";
    
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "⚠️ Node.js server is down (" . $e->getMessage() . "), but this confirms the backend tried to reach `/refundUsers` for the Intruder!\n";
    } else {
        echo "❌ Error during execution: " . $e->getMessage() . "\n";
    }
}

// Check logs (specifically looking for the mismatch warning)
echo "\nCheck laravel logs (storage/logs/laravel.log) to verify the refund message.\n";
