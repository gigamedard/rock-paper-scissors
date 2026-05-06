<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Fight;

$userId = 1;
$user = User::find($userId);

if (!$user) {
    echo "User #{$userId} not found.\n";
    exit;
}

echo "=== Historique des Combats du Bot #1 ({$user->wallet_address}) ===\n";
echo "Mise actuelle: {$user->bet_amount} ETH\n";
echo "Solde actuel: {$user->balance} ETH\n\n";

$fights = Fight::where('user1_id', $userId)
    ->orWhere('user2_id', $userId)
    ->orderBy('id')
    ->get();

$table = [];
foreach ($fights as $f) {
    $isUser1 = ($f->user1_id == $userId);
    $opponentId = $isUser1 ? $f->user2_id : $f->user1_id;
    $opponent = User::find($opponentId);
    
    $myMove = $isUser1 ? $f->user1_chosed : $f->user2_chosed;
    $oppMove = $isUser1 ? $f->user2_chosed : $f->user1_chosed;
    
    $outcome = "NUL";
    if ($f->result == 'user1_win') {
        $outcome = $isUser1 ? "VICTOIRE" : "DÉFAITE";
    } elseif ($f->result == 'user2_win') {
        $outcome = $isUser1 ? "DÉFAITE" : "VICTOIRE";
    }
    
    echo sprintf(
        "Combat #%d | Vs: Bot #%d (%s) | Mise: %s ETH | Résultat: %s\n",
        $f->id,
        $opponentId,
        substr($opponent->wallet_address, 0, 10) . '...',
        $f->base_bet_amount,
        $outcome
    );
    echo "           Mouvement: [{$myMove}] vs [{$oppMove}]\n\n";
}
