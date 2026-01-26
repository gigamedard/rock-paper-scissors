<?php

use App\Models\User;
use App\Models\ApiToken;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Setting up Admin User...\n";

// Find user or create 
$user = User::first();
if (!$user) {
    echo "No users found. Creating one.\n";
    $user = User::factory()->create([
        'wallet_address' => '0x' . bin2hex(random_bytes(20)),
        'name' => 'AdminUser'
    ]);
}

// Make Admin
$user->is_admin = true;
$user->save();

echo "User {$user->name} ({$user->wallet_address}) is now ADMIN.\n";

// Generate Token
// Assuming ApiToken model has this method as seen in Controller
try {
    $token = ApiToken::generateForUser($user, 60); // 60 minutes
    echo "\n-------------------------------------------------------\n";
    echo "ADMIN LOGIN LINK (Valid for 1 hour):\n";
    echo "http://127.0.0.1:8000/admin/login-via-token?token={$token}\n";
    echo "-------------------------------------------------------\n";
} catch (\Exception $e) {
    echo "Error generating token: " . $e->getMessage();
}
