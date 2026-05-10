<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::find(10);
if (!$user) {
    echo "User not found\n";
    exit;
}

$results = [
    'wallet' => $user->wallet_address,
    'initial_balance' => $user->session_start_balance,
    'current_balance' => $user->balance,
    'fights_count' => $user->fights()->count(),
    'fights' => $user->fights()->orderBy('created_at', 'asc')->get(['result', 'base_bet_amount', 'created_at'])->toArray()
];

echo json_encode($results, JSON_PRETTY_PRINT);
