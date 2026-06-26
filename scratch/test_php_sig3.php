<?php
require 'vendor/autoload.php';
use kornrunner\Keccak;
use Elliptic\EC;

$pk = 'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';
$hash = '8bae272da06e35dadbc0de5ea083fcf07eb36afa02fe57d79816eb5a5274ec34';
$prefix = "\x19Ethereum Signed Message:\n32";
$finalHash = Keccak::hash($prefix . hex2bin($hash), 256);

$ec = new EC('secp256k1');
$key = $ec->keyFromPrivate($pk);

$signature = $key->sign($finalHash, ['canonical' => true]);

$r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
$s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
$v = dechex($signature->recoveryParam + 27);

echo 'PHP Sig: 0x' . $r . $s . $v . PHP_EOL;
