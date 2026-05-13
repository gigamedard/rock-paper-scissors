<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\SignatureService;
use App\Helpers\Web3Helper;

$user = User::find(16);
if (!$user) {
    die("Error: User 16 not found!\n");
}

$user->balance = 10.05;
$user->status = 'stopped';

$signatureService = app(SignatureService::class);
$web3Helper = new Web3Helper();

$nodeUrl = config('app.NODE_WORKER_URL');
$contractAddress = env('BATTLEPOOL_ADDRESS');
$nonce = $web3Helper->getUserNonce($nodeUrl, $user->wallet_address);
$amountWei = $web3Helper->etherToWei(10.05);

$signature = $signatureService->generateClaimSignature(
    $user->wallet_address,
    $amountWei,
    $nonce,
    $contractAddress
);

$user->payout_signature = $signature;
$user->save();

echo "✅ Success: User 16 balance set to 10.05 ETH and signature generated.\n";
echo "Wallet: {$user->wallet_address}\n";
echo "Nonce: $nonce\n";
echo "Signature: $signature\n";
