<?php
return [
    'base_bet' => [1, 2, 4, 8,16,32,64,128,256,512,1024], // Example base_bet values
    'size' => [5],//[3, 5, 10,15],  Example size 
    //[100, 500, 1000, 5000], // Example size 
    'bach_size'=>3,
    'number_of_base_bet'=>11,
    'number_of_pool_size'=>4,
    'percentage_limit_of_pool_size'=>0.1,
    'batch_max_size' => 3, // Maximum number of pools a batch can hold
    'batch_initial_limit' => 50, // How many pools to grab when creating a new batch
    'batch_max_iterations' => 5,  // Max processing iterations before settling

];
