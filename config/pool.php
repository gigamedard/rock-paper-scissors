<?php
return [
    'base_bet' => [0.0004, 0.15], // Palier 1 ≈ 2000 XOF | Palier 2 ≈ 760 000 XOF (1 AVAX = 4900 XOF)
    'size' => [2], // Match contract defaultPoolMaxSize (set to 2 for simulation)
    //[100, 500, 1000, 5000], // Example size 
    'bach_size'=>3,
    'number_of_base_bet'=>2,
    'number_of_pool_size'=>4,
    'percentage_limit_of_pool_size'=>0.1,
    'batch_max_size' => 3, // Maximum number of pools a batch can hold
    'batch_initial_limit' => 50, // How many pools to grab when creating a new batch
    'batch_max_iterations' => 5,  // Max processing iterations before settling
    'max_martingale_level' => 4,  // Maximum doublings allowed (paliers de martingale inchangés)
    'batch_ttl_seconds' => env('BATCH_TTL_SECONDS', 1),
];
