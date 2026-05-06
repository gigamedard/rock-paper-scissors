<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Fight;
use App\Models\Pool;

$userId = 3;
$user = User::find($userId);

if (!$user) {
    echo "User #{$userId} not found.\n";
    exit;
}

echo "=== Audit Approfondi du Bot #1 ({$user->wallet_address}) ===\n";
echo "Mise actuelle: {$user->bet_amount} ETH\n";
echo "Balance finale en DB: {$user->balance} ETH\n\n";

$fights = Fight::where('user1_id', $userId)
    ->orWhere('user2_id', $userId)
    ->orderBy('id')
    ->get();

echo "Nombre total de combats trouvés: " . $fights->count() . "\n\n";

foreach ($fights as $f) {
    $isUser1 = ($f->user1_id == $userId);
    $opponentId = $isUser1 ? $f->user2_id : $f->user1_id;
    $opponent = User::find($opponentId);
    
    $outcome = "NUL";
    if ($f->result == 'user1_win') {
        $outcome = $isUser1 ? "VICTOIRE" : "DÉFAITE";
    } elseif ($f->result == 'user2_win') {
        $outcome = $isUser1 ? "DÉFAITE" : "VICTOIRE";
    }
    
    // Pour estimer la balance à ce moment, on va regarder le pool
    $pool = Pool::find($f->pool_id);
    
    echo sprintf(
        "Combat #%d | Pool ID: %d | Mise: %s ETH | Résultat: %s\n",
        $f->id,
        $f->pool_id,
        $f->base_bet_amount,
        $f->result
    );
    echo "           User1 ID: {$f->user1_id} (Move: {$f->user1_chosed}) | User2 ID: {$f->user2_id} (Move: {$f->user2_chosed})\n\n";
}

// Check session start balance
echo "Session Start Balance: {$user->session_start_balance} ETH\n";
