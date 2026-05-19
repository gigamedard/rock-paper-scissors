<?php 
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('wallet_address', '0x70997970C51812dc3A010C7d01b50e0d17dc79C8')
    ->first();

if ($user) {
    $user->token_balance = 10.0;
    $user->save();
    echo "OK: User balance updated to 10 SNT\n";
} else {
    echo "NOT FOUND\n";
}
