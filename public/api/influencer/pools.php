<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$pools = [
    [
        'id' => 1,
        'name' => 'Influenceurs Français',
        'language' => 'français',
        'milestone' => 5000,
        'pool_milestone' => 30000,
        'reward_amount' => 10,
        'current_referrals' => 24500,
        'progress_percentage' => 81.7,
        'eligible_influencers' => 5,
        'total_influencers' => 8
    ],
    [
        'id' => 2,
        'name' => 'English Influencers',
        'language' => 'english',
        'milestone' => 7500,
        'pool_milestone' => 50000,
        'reward_amount' => 25,
        'current_referrals' => 32100,
        'progress_percentage' => 64.2,
        'eligible_influencers' => 8,
        'total_influencers' => 12
    ],
    [
        'id' => 3,
        'name' => 'Influenciadores Españoles',
        'language' => 'español',
        'milestone' => 4000,
        'pool_milestone' => 20000,
        'reward_amount' => 8,
        'current_referrals' => 15800,
        'progress_percentage' => 79.0,
        'eligible_influencers' => 4,
        'total_influencers' => 6
    ]
];

echo json_encode($pools, JSON_PRETTY_PRINT);
?>

