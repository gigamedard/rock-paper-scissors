<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$u = User::find(1);
echo "balance (main): {$u->balance}\n";
echo "battle_balance: {$u->battle_balance}\n";
echo "total (balance + battle_balance): " . ($u->balance + $u->battle_balance) . "\n";
echo "status: {$u->status}\n";
echo "bet_amount: {$u->bet_amount}\n";
