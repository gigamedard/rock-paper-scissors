<?php
return [
    'bet_amounts'      => [1, 2, 4, 8, 16], // Dynamic bet amounts
    'depth_limit'      => 10,               // Maximum depth for slice_table
    'chunk_size'       => 10,               // Number of users to process at once
    'base_bet'         => 1,                // Base bet amount
    'privateKey'       =>'29f13333db1a6b87a39c26d0986a74306aa970aff4a26657ba2a782525d65867',
    'alchemyUrl'       => 'https://eth-sepolia.g.alchemy.com/v2/qGUwxK2NtwoK8xHN-qsQ7KJL5Bz9RBbo',
    'contractAddress'  => '0x5FbDB2315678afecb367f032d93F642f64180aa3',
    'abi'              =>json_decode(file_get_contents(base_path('resources/abi/Payment.json')), true),
    'security_coefficient' => 2048,
    'gain_coefficient' => 0.0001, // Coefficient for calculating gain
];
