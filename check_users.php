<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::find(1);
echo "User 1 Balance: " . ($user->balance ?? 'NULL') . "\n";
echo "User 1 Status: " . ($user->status ?? 'NULL') . "\n";
