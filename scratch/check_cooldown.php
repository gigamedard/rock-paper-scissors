<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (\App\Models\User::all() as $user) {
    if ($user->id === 1 || $user->id === 0) { // probably admin or first user
        echo "User ID {$user->id} ({$user->wallet_address}) Cooldown: {$user->cooldown_until} Recovery Level: {$user->recovery_level}\n";
    }
}
