<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Fight;

$fights = Fight::where('user1_id', 10)
    ->orWhere('user2_id', 10)
    ->orderBy('created_at', 'asc')
    ->get();

echo "| Pool ID | Bet (ETH) | Opponent | My Move | Their Move | Result |\n";
echo "| :--- | :--- | :--- | :--- | :--- | :--- |\n";

foreach ($fights as $f) {
    $isUser1 = ($f->user1_id == 10);
    $opponentId = $isUser1 ? $f->user2_id : $f->user1_id;
    $myMove = $isUser1 ? $f->user1_chosed : $f->user2_chosed;
    $theirMove = $isUser1 ? $f->user2_chosed : $f->user1_chosed;
    
    $result = 'DRAW';
    if ($f->result == 'user1_win') {
        $result = $isUser1 ? 'WIN' : 'LOSS';
    } elseif ($f->result == 'user2_win') {
        $result = $isUser1 ? 'LOSS' : 'WIN';
    }

    echo "| {$f->pool_id} | {$f->base_bet_amount} | Bot #{$opponentId} | {$myMove} | {$theirMove} | **{$result}** |\n";
}
