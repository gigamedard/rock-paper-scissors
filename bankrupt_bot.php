<?php
// bankrupt_bot.php
// Ce script met la balance du Bot 2 à zéro pour forcer son éjection lors de sa défaite
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Wallet address du Bot 2
$wallet = '0x90F79bf6EB2c4f870365E785982E1f101E93b906';
$user = App\Models\User::where('wallet_address', $wallet)->first();

if($user) {
    $user->balance = 0;
    $user->save();
    echo "✅ SUCCESS: Bot 2 ($wallet) est maintenant en FAILLITE (Balance = 0) ! Prêt pour le test d'éjection.\n";
} else {
    echo "❌ ERROR: Bot 2 non trouvé. Assurez-vous d'avoir lancé simulation_bots.js en premier.\n";
}
