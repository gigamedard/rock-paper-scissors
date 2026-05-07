<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Fight;

$userId = 4;
$u = User::find($userId);
if (!$u) {
    echo "User not found\n";
    exit;
}

echo "BALANCE_START\n";
echo "Main Balance: " . $u->balance . " ETH\n";
echo "Battle Balance: " . $u->battle_balance . " ETH\n";
echo "BALANCE_END\n";

$fights = Fight::where('user1_id', $userId)
    ->orWhere('user2_id', $userId)
    ->orderBy('created_at', 'asc')
    ->get();

echo "FIGHTS_START\n";
foreach ($fights as $f) {
    $isUser1 = ($f->user1_id == $userId);
    $opponentId = $isUser1 ? $f->user2_id : $f->user1_id;
    
    $myChoice = $isUser1 ? $f->user1_chosed : $f->user2_chosed;
    $oppChoice = $isUser1 ? $f->user2_chosed : $f->user1_chosed;
    
    $result = "DRAW";
    if ($f->result == 'user1_win') {
        $result = $isUser1 ? "WIN" : "LOSS";
    } elseif ($f->result == 'user2_win') {
        $result = $isUser1 ? "LOSS" : "WIN";
    }
    
    echo "F:{$f->id}|P:{$f->pool_id}|Opp:{$opponentId}|Me:{$myChoice}|OppCh:{$oppChoice}|Res:{$result}|Amt:{$f->base_bet_amount}|Date:{$f->created_at}\n";
}
echo "FIGHTS_END\n";
