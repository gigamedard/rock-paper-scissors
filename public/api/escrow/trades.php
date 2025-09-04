<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$trades = [
    [
        'id' => 1,
        'seller_address' => '0x1234...7890',
        'snt_amount' => 1000,
        'avax_amount' => 3.2,
        'price_per_snt' => 0.0032,
        'status' => 'active',
        'created_at' => date('c', time() - 7200),
        'expires_at' => date('c', time() + 79200),
        'is_own_trade' => false
    ],
    [
        'id' => 2,
        'seller_address' => '0x9876...4321',
        'snt_amount' => 750,
        'avax_amount' => 2.1,
        'price_per_snt' => 0.0028,
        'status' => 'active',
        'created_at' => date('c', time() - 18000),
        'expires_at' => date('c', time() + 68400),
        'is_own_trade' => false
    ],
    [
        'id' => 3,
        'seller_address' => '0x1111...1111',
        'snt_amount' => 2500,
        'avax_amount' => 7.8,
        'price_per_snt' => 0.00312,
        'status' => 'active',
        'created_at' => date('c', time() - 3600),
        'expires_at' => date('c', time() + 82800),
        'is_own_trade' => true
    ]
];

$response = [
    'trades' => $trades,
    'pagination' => [
        'current_page' => 1,
        'total_pages' => 1,
        'total_trades' => count($trades)
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>

