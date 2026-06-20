<?php

use App\Models\User;
use App\Models\Referral;
use App\Services\ReferralService;

// Nettoyage
User::where('email', 'like', 'test_ref%')->delete();
Referral::where('referrer_id', '>', 0)->delete(); // On nettoie tout

// 1. Parrain et Filleul
$referrer = User::factory()->create([
    'email' => 'test_ref_parrain@battlepool.com',
    'wallet_address' => '0xRef1',
    'token_balance' => 0,
    'is_eligible_to_refer' => true
]);

$referred = User::factory()->create([
    'email' => 'test_ref_filleul@battlepool.com',
    'wallet_address' => '0xRef2',
    'token_balance' => 0,
    'has_received_signup_bonus' => false
]);

// 2. Parrainage en attente
$referral = Referral::create([
    'referrer_id' => $referrer->id,
    'referred_id' => $referred->id,
    'status' => 'pending'
]);

echo "\n--- AVANT VALIDATION ---\n";
echo "Filleul SNT: {$referred->token_balance}\n";
echo "Parrain SNT: {$referrer->token_balance}\n";

// 3. Traitement
$service = new ReferralService();
$service->processReferralValidation($referred);

$referrer->refresh();
$referred->refresh();
$referral->refresh();

echo "\n--- APRES VALIDATION ---\n";
echo "Filleul SNT: {$referred->token_balance} (Attendu: 1)\n";
echo "Parrain SNT: {$referrer->token_balance} (Attendu: 1)\n";
echo "Statut Parrainage: {$referral->status} (Attendu: validated)\n";

// Nettoyage final
User::where('email', 'like', 'test_ref%')->delete();
echo "\nTest Parrainage terminé.\n";
