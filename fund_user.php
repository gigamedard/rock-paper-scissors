<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::firstOrCreate(
    ['wallet_address' => '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266'],
    [
        'name' => 'UI Test User',
        'email' => 'ui-test@game.web3',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]
);

$user->update(['balance' => 100]);
event(new \App\Events\BalanceUpdated($user->id, 100));
echo "✅ Balance updated to: " . $user->balance . " ETH for " . $user->wallet_address . "\n";
echo "📡 BalanceUpdated event dispatched via Reverb!\n";
