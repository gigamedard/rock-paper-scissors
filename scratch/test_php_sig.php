<?php
require 'vendor/autoload.php';
use kornrunner\Keccak;

function encodePacked($items) {
    $result = '';
    foreach ($items as $item) {
        [$type, $value] = $item;
        if ($type === 'address') {
            $clean = str_starts_with($value, '0x') ? substr($value, 2) : $value;
            $result .= str_pad(strtolower($clean), 40, '0', STR_PAD_LEFT);
        } elseif ($type === 'uint256') {
            $hex = gmp_strval(gmp_init($value), 16);
            $result .= str_pad($hex, 64, '0', STR_PAD_LEFT);
        }
    }
    return $result;
}

$data = encodePacked([
    ['address', '0xfe0f143fcad5b561b1ed2ac960278a2f23559ef9'],
    ['uint256', '50000000000000000000'],
    ['uint256', '0'],
    ['address', '0x5FbDB2315678afecb367f032d93F642f64180aa3']
]);

$hash = Keccak::hash(hex2bin($data), 256);
echo 'Data: 0x' . $data . PHP_EOL;
echo 'Hash: 0x' . $hash . PHP_EOL;
