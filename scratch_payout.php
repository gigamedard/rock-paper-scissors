<?php

use App\Models\User;
use App\Services\SessionManager;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sm = app(SessionManager::class);
$users = User::where('status', 'stopped')->where('balance', '>', 0)->get();

echo "Processing payouts for " . $users->count() . " users...\n";

$reflection = new ReflectionClass(SessionManager::class);
$method = $reflection->getMethod('sendPayment');
$method->setAccessible(true);

foreach ($users as $user) {
    echo "Sending payment to {$user->wallet_address} (Balance: {$user->balance} ETH)...\n";
    $method->invoke($sm, $user);
    
    // Clear balance and set status to available so they can play again
    $user->balance = 0;
    $user->status = 'available';
    $user->save();
}

echo "Done.\n";
