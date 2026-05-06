<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Pool;

$user = User::find(10);
$poolIds = \DB::table('users')->where('id', 10)->pluck('pool_id')->toArray();
// Wait! users table only has CURRENT pool_id.
// I should check the pool_history or just fights.

// Let's check all unique pool_id from fights for user 10
$poolIds = \App\Models\Fight::where('user1_id', 10)->orWhere('user2_id', 10)->distinct()->pluck('pool_id')->toArray();

foreach ($poolIds as $pid) {
    $p = Pool::find($pid);
    echo "Pool: {$p->id} | Bet: {$p->base_bet}\n";
}
