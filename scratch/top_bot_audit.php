<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Fight;

// Find user with most total fights (user1 or user2)
$userId = 7;
$user = User::find($userId);

if (!$user) {
    echo "User #{$userId} not found.\n";
    exit;
}

echo "Analyzing User: {$user->wallet_address} (ID: {$user->id})\n";
echo "Current Bet: {$user->bet_amount} ETH | Balance: {$user->balance} ETH\n\n";

$fights = Fight::where('user1_id', $user->id)
    ->orWhere('user2_id', $user->id)
    ->orderBy('id')
    ->get();

foreach ($fights as $f) {
    $role = ($f->user1_id == $user->id) ? "User1" : "User2";
    $opponentId = ($role == "User1") ? $f->user2_id : $f->user1_id;
    $outcome = "DRAW";
    if ($f->result == 'user1_win') $outcome = ($role == "User1") ? "WIN" : "LOSS";
    if ($f->result == 'user2_win') $outcome = ($role == "User2") ? "WIN" : "LOSS";
    
    echo "Fight #{$f->id} | Pool: {$f->pool_id} | Role: {$role} | vs Bot #{$opponentId} | Result: {$f->result} | Outcome: {$outcome} | Bet: {$f->base_bet_amount}\n";
}
