<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');
echo json_construct([
    'APP_ENV' => env('APP_ENV'),
    'config_app_env' => config('app.env'),
    'recovery_time' => config('game_levels.recovery_time.1'),
]);

function json_construct($data) {
    return json_encode($data, JSON_PRETTY_PRINT);
}
