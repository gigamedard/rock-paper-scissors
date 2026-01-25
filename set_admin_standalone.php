<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\User;

$newAdmin = '0x64045bF2d2dE90C60FC417003906704A63B9A441';
$oldAdmin = '0x13681EbA8A5eFDBB53e5689c16C86014eA2DBe16';

// 1. Set new admin
$user = User::where('wallet_address', $newAdmin)->first();
if ($user) {
    $user->is_admin = true;
    $user->save();
    echo "User found (ID: {$user->id}, Address: {$user->wallet_address}) and set to ADMIN.\n";
} else {
    echo "New Admin User NOT FOUND: $newAdmin\n";
    // Check if we need to create it? No, usually admin should be an existing user.
    // Ensure we create it if it doesn't exist? The user might expect it.
    // Let's create it if missing for convenience?
    // User::create(['wallet_address' => $newAdmin, 'is_admin' => true, ...]);
    // Better to just report.
}

// 2. Unset old admin
$oldUser = User::where('wallet_address', $oldAdmin)->first();
if ($oldUser) {
    $oldUser->is_admin = false;
    $oldUser->save();
    echo "Old Admin (ID: {$oldUser->id}) stripped of admin privileges.\n";
}

