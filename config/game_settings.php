<?php
return [
    'bet_amounts'      => [1, 2, 4, 8, 16], // Dynamic bet amounts
    'depth_limit'      => 10,               // Maximum depth for slice_table
    'chunk_size'       => 10,               // Number of users to process at once
    'base_bet'         => 0.01,                // Base bet amount (0.01 ETH)
    'privateKey'       => env('GAME_WALLET_PK'),
    'alchemyUrl'       => env('ALCHEMY_URL'),
    'contractAddress'  => env('BATTLEPOOL_ADDRESS', '0x5FbDB2315678afecb367f032d93F642f64180aa3'),
    'abi'              =>json_decode(file_get_contents(base_path('resources/abi/Payment.json')), true),
    'security_coefficient' => 1000,
    'gain_coefficient' => 0.0001, // Coefficient for calculating gain
];
