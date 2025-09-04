<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$stats = [
    'total_trades' => 156,
    'active_trades' => 23,
    'total_snt_volume' => 45200,
    'total_avax_volume' => 128.7,
    'average_price' => 0.00285,
    'last_24h_trades' => 12,
    'last_24h_volume_snt' => 8500,
    'last_24h_volume_avax' => 24.3
];

echo json_encode($stats, JSON_PRETTY_PRINT);
?>

