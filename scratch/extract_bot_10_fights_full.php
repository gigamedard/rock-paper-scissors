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

foreach ($fights as $f) {
    echo "ID: {$f->id} | Pool: {$f->pool_id} | Bet: {$f->base_bet_amount} | Created: {$f->created_at}\n";
}
