<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\SessionManager;

$user = User::find(1);
if (!$user) {
    die("User 26 not found\n");
}

echo "Attempting manual payout for User 26 ({$user->wallet_address}) with balance {$user->balance} ETH...\n";

$sessionManager = $app->make(SessionManager::class);

// Use reflection or a public wrapper if sendPayment is private
// In SessionManager.php, sendPayment is private. I'll use a public method or just call the logic.
// Actually, I'll just call the Web3Helper directly like SessionManager does.

$web3Helper = $app->make(\App\Helpers\Web3Helper::class);
try {
    $nodeUrl = config('app.NODE_WORKER_URL');
    echo "Sending request to Node Worker at {$nodeUrl}...\n";
    $web3Helper->sendPayement($nodeUrl, $user->wallet_address, $user->balance);
    echo "✅ Success! Check Hardhat logs for eth_sendTransaction.\n";
} catch (\Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
}
