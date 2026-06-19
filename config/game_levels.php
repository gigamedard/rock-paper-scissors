<?php

return [
    'recovery_time' => [
        0 => env('APP_ENV') === 'local' ? 2 : 24 * 60,  // 2 minutes en local, sinon 24h
        1 => 16 * 60,
        2 => 8 * 60,
        3 => 4 * 60,
        4 => 2 * 60,
        5 => 30,       // 30 minutes
    ],
    'multiplier' => [
        0 => 1.08,
        1 => 4.0,
        2 => 7.5,
        3 => 15.0,
        4 => 20.0,
        5 => 100.0,
    ]
];
