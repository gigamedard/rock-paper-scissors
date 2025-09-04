<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$whitelist = [
    'merkle_root' => '0x' . bin2hex(random_bytes(32)),
    'total_addresses' => 156,
    'generated_at' => date('c'),
    'min_balance' => 100
];

echo json_encode($whitelist, JSON_PRETTY_PRINT);
?>

