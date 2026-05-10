<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::all();
foreach ($users as $user) {
    echo "ID: " . $user->id . " | Wallet: " . $user->wallet_address . " | Balance: " . $user->balance . "\n";
}
