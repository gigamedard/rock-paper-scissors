<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$leaderboard = [
    [
        'rank' => 1,
        'name' => 'CryptoMaster',
        'pool_name' => 'English Influencers',
        'referral_count' => 8500,
        'total_avax_spent' => 28.5,
        'conversion_rate' => 85.2,
        'has_claimed_reward' => true
    ],
    [
        'rank' => 2,
        'name' => 'Web3Guru',
        'pool_name' => 'Influenceurs Français',
        'referral_count' => 6200,
        'total_avax_spent' => 22.1,
        'conversion_rate' => 78.9,
        'has_claimed_reward' => false
    ],
    [
        'rank' => 3,
        'name' => 'BlockchainPro',
        'pool_name' => 'English Influencers',
        'referral_count' => 5800,
        'total_avax_spent' => 19.7,
        'conversion_rate' => 72.4,
        'has_claimed_reward' => true
    ]
];

echo json_encode($leaderboard, JSON_PRETTY_PRINT);
?>

