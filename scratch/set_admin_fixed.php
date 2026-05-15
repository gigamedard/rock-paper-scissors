<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$wallet = strtolower("0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266");
$user = User::firstOrCreate(['wallet_address' => $wallet]);

$user->is_admin = true;
$user->save();

echo "User {$wallet} is now ADMIN.\n";
