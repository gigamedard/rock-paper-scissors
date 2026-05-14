<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::where('id', '<', 10)->get();
foreach ($users as $u) {
    echo "ID: {$u->id} | Wallet: {$u->wallet_address} | Status: {$u->status} | Bal: {$u->balance} | BatBal: {$u->battle_balance} | Bet: {$u->bet_amount} | StartBal: {$u->session_start_balance}\n";
}
