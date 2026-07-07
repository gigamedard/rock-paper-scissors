<?php
$contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
$response = Illuminate\Support\Facades\Http::post('http://blockchain:8545', [
    'jsonrpc' => '2.0',
    'method' => 'eth_getBalance',
    'params' => [$contractAddress, 'latest'],
    'id' => 1
]);
$result = $response->json();
$balanceHex = $result['result'];
$balanceWei = hexdec($balanceHex);
$balanceEth = $balanceWei / 1e18;
echo "Contract Balance: " . $balanceEth . " ETH\n";
