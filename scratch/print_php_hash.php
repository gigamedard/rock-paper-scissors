<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SignatureService;

$userAddress = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
$amountWei = "10800000000000000000"; // 10.8 ETH in Wei
$nonce = 0;
$contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";

$signatureService = app(SignatureService::class);

// Let's copy the encodePacked logic to debug
$reflection = new \ReflectionClass(SignatureService::class);
$method = $reflection->getMethod('encodePacked');
$method->setAccessible(true);

$data = $method->invoke($signatureService, [
    ['address', $userAddress],
    ['uint256', $amountWei],
    ['uint256', (string)$nonce],
    ['address', $contractAddress]
]);

$hash = \kornrunner\Keccak::hash(hex2bin($data), 256);
echo "PHP data: " . $data . "\n";
echo "PHP Keccak256 hash: 0x" . $hash . "\n";
