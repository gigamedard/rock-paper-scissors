<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "APP_ENV: " . env('APP_ENV') . "\n";
echo "config('app.env'): " . config('app.env') . "\n";
echo "config('game_levels.recovery_time.1'): " . config('game_levels.recovery_time.1') . "\n";
