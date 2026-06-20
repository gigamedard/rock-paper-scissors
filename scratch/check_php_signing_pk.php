<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pk = env('GAME_WALLET_PK');
echo "Laravel GAME_WALLET_PK: " . $pk . "\n";
