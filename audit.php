<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::where('autoplay_active', true)->get();
$totalUsers = $users->count();
$stoppedUsers = $users->where('status', 'stopped')->count();
$availableUsers = $users->where('status', 'available')->count();
$inPoolUsers = $users->where('status', 'in_pool')->count();

$zeroStartBalance = $users->where('session_start_balance', 0)->count();

$martingaleStats = $users->groupBy('bet_amount')->map->count();

$fightsCount = App\Models\Fight::count();
$poolsCount = App\Models\Pool::count();

echo "=== AUDIT RAPPORT ===\n";
echo "Total Users: {$totalUsers}\n";
echo "Stopped: {$stoppedUsers}, Available: {$availableUsers}, In Pool: {$inPoolUsers}\n";
echo "Users with session_start_balance = 0: {$zeroStartBalance}\n";
echo "Martingale Tiers (bet_amount => count):\n";
foreach ($martingaleStats as $tier => $count) {
    echo "  {$tier} ETH => {$count} users\n";
}
echo "Total Fights: {$fightsCount}\n";
echo "Total Pools: {$poolsCount}\n";

echo "\nStopped Users Detail:\n";
foreach ($users->where('status', 'stopped') as $u) {
    $q = $u->session_start_balance > 0 ? ($u->balance / $u->session_start_balance) : 0;
    echo "  User {$u->id} (Wallet: " . substr($u->wallet_address, 0, 6) . "): bal={$u->balance}, start_bal={$u->session_start_balance}, bet={$u->bet_amount}, q={$q}\n";
}

echo "\nTop 5 Martingale Users:\n";
foreach ($users->sortByDesc('bet_amount')->take(5) as $u) {
    echo "  User {$u->id}: bet_amount={$u->bet_amount}, balance={$u->balance}\n";
}
