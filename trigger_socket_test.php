<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Events\BalanceUpdated;

// Find the user with mock address
$user = User::where('wallet_address', '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266')->first();

if (!$user) {
    die("User not found. Login in the UI first!\n");
}

echo "Triggering BalanceUpdated for User {$user->id}...\n";
$newBalance = "123.4567";

// Dispatch event
event(new BalanceUpdated($user, $newBalance));

echo "✅ Event dispatched. Check the UI for 123.4567 ETH!\n";
