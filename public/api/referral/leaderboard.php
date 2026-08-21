<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$leaderboard = [
    [
        'rank' => 1,
        'name' => 'CryptoMaster',
        'wallet_address' => '0x1234...7890',
        'referral_count' => 45,
        'total_rewards' => 4500
    ],
    [
        'rank' => 2,
        'name' => 'BlockchainPro',
        'wallet_address' => '0x9876...4321',
        'referral_count' => 32,
        'total_rewards' => 3200
    ],
    [
        'rank' => 3,
        'name' => 'Web3Guru',
        'wallet_address' => '0x5555...5555',
        'referral_count' => 28,
        'total_rewards' => 2800
    ],
    [
        'rank' => 4,
        'name' => 'DeFiExpert',
        'wallet_address' => '0xaaaa...aaaa',
        'referral_count' => 22,
        'total_rewards' => 2200
    ],
    [
        'rank' => 5,
        'name' => 'NFTCollector',
        'wallet_address' => '0xbbbb...bbbb',
        'referral_count' => 18,
        'total_rewards' => 1800
    ],
    [
        'rank' => 6,
        'name' => 'Test User',
        'wallet_address' => '0x1111...1111',
        'referral_count' => 15,
        'total_rewards' => 1500
    ]
];

echo json_encode($leaderboard, JSON_PRETTY_PRINT);
?>

