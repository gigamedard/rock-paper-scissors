<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::all();
foreach ($users as $user) {
    echo "User ID: {$user->id}, Wallet: {$user->wallet_address}, Balance: {$user->balance}, Autoplay: " . ($user->autoplay_active ? 'Yes' : 'No') . ", Status: {$user->status}, Bet: {$user->bet_amount}\n";
}
