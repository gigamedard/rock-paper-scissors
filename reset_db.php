<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('wallet_address', 'like', '%13681EBA8a5eFDbB53e5689C16C86014Ea2dBe16%')->first();
if ($user) {
    echo "Found user ID: " . $user->id . "\n";
    $user->status = 'available';
    $user->session_started = false;
    $user->autoplay_active = false;
    $user->battle_balance = 0;
    $user->session_start_battle_balance = 0;
    $user->save();
    
    echo "User unstuck in DB!\n";
} else {
    echo "User not found.\n";
}
