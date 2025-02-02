<?php
return [
    'bet_amounts'      => [1, 2, 4, 8, 16], // Dynamic bet amounts
    'depth_limit'      => 10,               // Maximum depth for slice_table
    'chunk_size'       => 10,               // Number of users to process at once
    'base_bet'         => 1,                // Base bet amount
    'privateKey'       =>'29f13333db1a6b87a39c26d0986a74306aa970aff4a26657ba2a782525d65867',
    'alchemyUrl'       => 'https://eth-sepolia.g.alchemy.com/v2/qGUwxK2NtwoK8xHN-qsQ7KJL5Bz9RBbo',
    'contractAddress'  => '0x9fE46736679d2D9a65F0992F2272dE9f3c7fa6e0',
    'abi'              =>json_decode(file_get_contents(base_path('resources/abi/Payment.json')), true),
    'security_coefficient' => 1000,
];
