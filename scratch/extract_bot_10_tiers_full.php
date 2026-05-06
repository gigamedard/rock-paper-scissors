<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Fight;
use App\Models\Pool;

$fights = Fight::where('user1_id', 10)
    ->orWhere('user2_id', 10)
    ->orderBy('created_at', 'asc')
    ->get();

foreach ($fights as $f) {
    $p = Pool::find($f->pool_id);
    $tier = $p ? $p->base_bet : 'UNKNOWN';
    echo "ID: {$f->id} | Pool: {$f->pool_id} | Tier: {$tier} | Created: {$f->created_at} | Result: {$f->result}\n";
}
