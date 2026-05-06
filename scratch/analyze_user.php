<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Fight;

$user = User::withCount('fights')
    ->get()
    ->sortByDesc('fights_count')
    ->first();
if (!$user) {
    echo "No user found at 0.04 level.\n";
    exit;
}

echo "Analyzing User: {$user->wallet_address} (ID: {$user->id})\n";
echo "Current Bet Amount: {$user->bet_amount}\n";
echo "Current Balance: {$user->balance}\n";

$fights = Fight::where('user1_id', $user->id)
    ->orWhere('user2_id', $user->id)
    ->orderBy('id')
    ->get();

echo "\nFight History:\n";
foreach ($fights as $f) {
    $role = ($f->user1_id == $user->id) ? "User1" : "User2";
    $opponentId = ($role == "User1") ? $f->user2_id : $f->user1_id;
    
    $outcome = "DRAW";
    if ($f->result == 'user1_win') {
        $outcome = ($role == "User1") ? "WIN" : "LOSS";
    } elseif ($f->result == 'user2_win') {
        $outcome = ($role == "User2") ? "WIN" : "LOSS";
    }
    
    echo "ID: {$f->id} | Role: {$role} | vs User: {$opponentId} | Result: {$f->result} | Outcome: {$outcome}\n";
}
