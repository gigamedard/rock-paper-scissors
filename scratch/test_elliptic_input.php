<?php
require __DIR__.'/../vendor/autoload.php';

use Elliptic\EC;
use kornrunner\Keccak;

$userAddress = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
$amountWei = "10800000000000000000"; // 10.8 ETH in Wei
$nonce = 0;
$contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
$privateKey = "ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80";

// encodePacked manually
$data = "70997970c51812dc3a010c7d01b50e0d17dc79c8" . 
        str_pad(gmp_strval(gmp_init($amountWei), 16), 64, '0', STR_PAD_LEFT) . 
        str_pad(gmp_strval(gmp_init($nonce), 16), 64, '0', STR_PAD_LEFT) . 
        "5fbdb2315678afecb367f032d93f642f64180aa3";

$hash = Keccak::hash(hex2bin($data), 256);
$prefix = "\x19Ethereum Signed Message:\n32";
$finalHash = Keccak::hash($prefix . hex2bin($hash), 256);

$ec = new EC('secp256k1');
$key = $ec->keyFromPrivate($privateKey);

function publicKeyToAddress($pubkeyHex) {
    if (strpos($pubkeyHex, '04') === 0) {
        $pubkeyHex = substr($pubkeyHex, 2);
    }
    $hash = Keccak::hash(hex2bin($pubkeyHex), 256);
    return '0x' . substr($hash, -40);
}

// Option A: Sign hex string directly
$sigA = $key->sign($finalHash, ['canonical' => true]);
$rA = str_pad($sigA->r->toString(16), 64, '0', STR_PAD_LEFT);
$sA = str_pad($sigA->s->toString(16), 64, '0', STR_PAD_LEFT);
$pubkeyA = $ec->recoverPubKey($finalHash, [
    'r' => gmp_init($rA, 16),
    's' => gmp_init($sA, 16)
], $sigA->recoveryParam);
echo "Option A recovered: " . publicKeyToAddress($pubkeyA->encode('hex')) . "\n";
echo "Option A sig: 0x" . $rA . $sA . dechex($sigA->recoveryParam + 27) . "\n";
