<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Fight;

echo "--- LISTE DES UTILISATEURS ---\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id} | Wallet: {$u->wallet_address} | Name: {$u->name}\n";
}

echo "\n--- TOUS LES COMBATS RECENTS ---\n";
$fights = Fight::latest()->take(20)->get();
foreach ($fights as $f) {
    echo "ID: {$f->id} | Pool: {$f->pool_id} | User1: {$f->user1_id} | User2: {$f->user2_id} | Result: {$f->result} | {$f->user1_chosed} vs {$f->user2_chosed}\n";
}
