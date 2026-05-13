<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$addr = '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266';
$user = \App\Models\User::where('wallet_address', $addr)->first();

if ($user) {
    $user->is_admin = true;
    $user->save();
    echo "User {$addr} is now ADMIN.\n";
} else {
    $user = \App\Models\User::create([
        'name' => 'MainAdmin',
        'wallet_address' => $addr,
        'is_admin' => true,
        'email' => 'admin@battlepool.com',
        'password' => bcrypt('password')
    ]);
    echo "Created Admin User: {$addr}\n";
}
