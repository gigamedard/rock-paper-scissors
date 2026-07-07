<?php
$user = App\Models\User::find(1);
$influencer = $user->influencer;

echo "--- STATUT DU CLAIM ---\n";
echo "Wallet: {$user->wallet_address}\n";
echo "Token Balance (SNT): {$user->token_balance}\n";
echo "Has Claimed (Influencer): " . ($influencer->has_claimed ? "OUI" : "NON") . "\n";

// Let's also check if there is any pending transaction or payout
$payoutSignature = $user->payout_signature ?? 'Aucune';
echo "Payout Signature: {$payoutSignature}\n";
