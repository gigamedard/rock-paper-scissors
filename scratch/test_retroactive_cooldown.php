<?php

use App\Models\User;
use App\Models\Card;
use App\Models\UserCard;
use App\Jobs\VerifyCardPurchaseJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

// 1. Setup Data
User::where('email', 'like', 'test_retro%')->delete();
Card::where('name', 'like', 'TestCard Retro%')->delete();

$user = User::factory()->create([
    'email' => 'test_retro@battlepool.com',
    'wallet_address' => '0xRetro123',
    'recovery_level' => 1 // Ensures base cooldown is 16h (960 mins)
]);

$card = Card::create([
    'name' => 'TestCard Retro Cooldown',
    'image_url' => 'test.png',
    'rarity' => 'legendary',
    'effect_type' => 'cooldown_reduction',
    'effect_value' => 0.5, // 50% reduction
    'price' => 100
]);

echo "--- ETAPE 1: Utilisateur en Cooldown ---\n";
// The base cooldown should be 960 minutes (16h).
// We set cooldown_until to 10 hours from now (meaning he has waited 6 hours).
$user->cooldown_until = now()->addHours(10);
$user->save();

echo "Old Cooldown Until: {$user->cooldown_until}\n";
$remainingMinutes = Carbon::parse($user->cooldown_until)->diffInMinutes(now());
echo "Remaining time: {$remainingMinutes} minutes\n";

// 2. Buy Card
echo "\n--- ETAPE 2: Achat et Activation de la carte ---\n";
$userCard = UserCard::create([
    'user_id' => $user->id,
    'card_id' => $card->id,
    'status' => 'pending',
    'tx_hash' => '0xMockTxRetro123'
]);

// Mock the Web3Helper to bypass on-chain verification
app()->bind(\App\Helpers\Web3Helper::class, function () {
    return new class {
        public function verifySntTransfer() {
            return ['success' => true];
        }
        public function setUserNextSessionTime() {
            return ['success' => true];
        }
        public function setUserLimits() {
            return ['success' => true];
        }
    };
});

// Run Job
$job = new VerifyCardPurchaseJob($userCard);
$job->handle();

$user->refresh();
echo "\n--- ETAPE 3: Resultat ---\n";
echo "New Cooldown Until: " . ($user->cooldown_until ?? 'NULL (Cooldown fini !)') . "\n";
if ($user->cooldown_until) {
    $newRemainingMinutes = Carbon::parse($user->cooldown_until)->diffInMinutes(now());
    echo "New Remaining time: {$newRemainingMinutes} minutes\n";
    echo "Reduction Appliquée: " . ($remainingMinutes - $newRemainingMinutes) . " minutes\n";
} else {
    echo "Le joueur est instantanement debloque ! \n";
}

// Cleanup
User::where('email', 'like', 'test_retro%')->delete();
Card::where('name', 'like', 'TestCard Retro%')->delete();
echo "\nTest de Cooldown Retroactif Termine.\n";
