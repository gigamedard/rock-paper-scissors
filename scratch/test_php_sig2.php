<?php
require 'vendor/autoload.php';
use kornrunner\Keccak;

$hash = '8bae272da06e35dadbc0de5ea083fcf07eb36afa02fe57d79816eb5a5274ec34';
$prefix = "\x19Ethereum Signed Message:\n32";
$finalHash = Keccak::hash($prefix . hex2bin($hash), 256);
echo 'Final Hash: 0x' . $finalHash . PHP_EOL;
