<?php

use App\Models\User;
use App\Models\Card;
use App\Models\UserCard;
use App\Services\SessionManager;
use Illuminate\Support\Facades\DB;

// Nettoyage pour le test
User::where('email', 'like', 'test_card%')->delete();
Card::where('name', 'like', 'TestCard%')->delete();

// 1. Créer un utilisateur de test
$user = User::factory()->create([
    'email' => 'test_cards@battlepool.com',
    'wallet_address' => '0xTestCardUser123'
]);

echo "\n--- TEST 1 : Limites SANS carte ---\n";
$limits = $user->getActiveLimits();
echo "Max Base Bet: {$limits['max_base_bet']} ETH\n";
echo "Max Q (Ceiling): {$limits['max_q']}\n";
echo "Min Cooldown: {$limits['min_cooldown']} minutes\n";

$initialBaseBet = $limits['max_base_bet'];
$initialCeiling = $limits['max_q'];
$initialCooldown = $limits['min_cooldown'];

// 2. Créer des cartes de test
$cardBet = Card::create([
    'name' => 'TestCard Bet Modifier',
    'image_url' => 'test.png',
    'rarity' => 'common',
    'effect_type' => 'base_bet_modifier',
    'effect_value' => 0.05, // +0.05 ETH
    'price' => 100
]);

$cardCeiling = Card::create([
    'name' => 'TestCard Ceiling Increase',
    'image_url' => 'test.png',
    'rarity' => 'rare',
    'effect_type' => 'ceiling_increase',
    'effect_value' => 0.5, // +0.5 to multiplier
    'price' => 100
]);

$cardCooldown = Card::create([
    'name' => 'TestCard Cooldown Reduction',
    'image_url' => 'test.png',
    'rarity' => 'legendary',
    'effect_type' => 'cooldown_reduction',
    'effect_value' => 0.2, // -20% cooldown
    'price' => 100
]);

echo "\n--- TEST 2 : Ajouter la carte Base Bet (+0.05) ---\n";
UserCard::create([
    'user_id' => $user->id,
    'card_id' => $cardBet->id,
    'is_equipped' => true,
    'acquired_at' => now(),
]);

$user->refresh();
$limits = $user->getActiveLimits();
echo "Max Base Bet: {$limits['max_base_bet']} ETH (Attendu: " . ($initialBaseBet + 0.05) . ")\n";

echo "\n--- TEST 3 : Ajouter la carte Ceiling (+0.5) ---\n";
UserCard::create([
    'user_id' => $user->id,
    'card_id' => $cardCeiling->id,
    'is_equipped' => true,
    'acquired_at' => now(),
]);

$user->refresh();
$limits = $user->getActiveLimits();
echo "Max Q (Ceiling): {$limits['max_q']} (Attendu: " . ($initialCeiling + 0.5) . ")\n";

echo "\n--- TEST 4 : Ajouter la carte Cooldown (-20%) ---\n";
UserCard::create([
    'user_id' => $user->id,
    'card_id' => $cardCooldown->id,
    'is_equipped' => true,
    'acquired_at' => now(),
]);

$user->refresh();
$limits = $user->getActiveLimits();
echo "Recovery Level: {$user->recovery_level}\n";
echo "APP_ENV: " . env('APP_ENV') . "\n";
echo "Min Cooldown: {$limits['min_cooldown']} minutes (Attendu: " . ($initialCooldown * 0.8) . ")\n";

// Nettoyage final
User::where('email', 'like', 'test_card%')->delete();
Card::where('name', 'like', 'TestCard%')->delete();
echo "\nTests terminés.\n";

