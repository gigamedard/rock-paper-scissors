<?php
return [
    'base_bet' => [0.01, 0.02, 0.04, 0.08, 0.16, 0.32, 0.64, 1.28, 2.56, 5.12, 10.24], // 0.01 ETH base
    'size' => [2], // Match contract defaultPoolMaxSize (set to 2 for simulation)
    //[100, 500, 1000, 5000], // Example size 
    'bach_size'=>3,
    'number_of_base_bet'=>11,
    'number_of_pool_size'=>4,
    'percentage_limit_of_pool_size'=>0.1,
    'batch_max_size' => 3, // Maximum number of pools a batch can hold
    'batch_initial_limit' => 50, // How many pools to grab when creating a new batch
    'batch_max_iterations' => 5,  // Max processing iterations before settling
    'max_martingale_level' => 4,  // Maximum doublings allowed (e.g. 0.01 -> 0.02 -> 0.04 -> 0.08 -> 0.16 -> RESET)
    'batch_ttl_seconds' => env('BATCH_TTL_SECONDS', 1),
];
