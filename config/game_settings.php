<?php
return [
    'bet_amounts'      => [1, 2, 4, 8, 16], // Dynamic bet amounts
    'depth_limit'      => 10,               // Maximum depth for slice_table
    'chunk_size'       => 10,               // Number of users to process at once
    'base_bet'         => 1,                // Base bet amount
    'privateKey'       =>'***REMOVED***',
    'alchemyUrl'       => 'https://eth-sepolia.g.alchemy.com/v2/qGUwxK2NtwoK8xHN-qsQ7KJL5Bz9RBbo',
    'contractAddress'  => '0xCf7Ed3AccA5a467e9e704C703E8D87F634fB0Fc9',
    'abi'              =>json_decode(file_get_contents(base_path('resources/abi/Payment.json')), true),
    'security_coefficient' => 1000,
];
