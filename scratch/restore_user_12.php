<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\SignatureService;
use App\Helpers\Web3Helper;

// User 12 is 0x70997970c51812dc3a010c7d01b50e0d17dc79c8 (Hardhat Account #1)
$user = User::find(12);
if (!$user) {
    die("Error: User 12 not found!\n");
}

$user->balance = 10.8;
$user->battle_balance = 0;
$user->status = 'stopped';

$signatureService = app(SignatureService::class);
$web3Helper = new Web3Helper();

$nodeUrl = config('app.NODE_WORKER_URL');
$contractAddress = config('app.BATTLEPOOL_ADDRESS');
$nonce = $web3Helper->getUserNonce($nodeUrl, $user->wallet_address);
$amountWei = $web3Helper->etherToWei(10.8);

$signature = $signatureService->generateClaimSignature(
    $user->wallet_address,
    $amountWei,
    $nonce,
    $contractAddress
);

$user->payout_signature = $signature;
$user->save();

echo "✅ Success: User 12 balance set to 10.8 ETH, status set to 'stopped', and claim signature generated.\n";
echo "Wallet: {$user->wallet_address}\n";
echo "Nonce: $nonce\n";
echo "Signature: $signature\n";
