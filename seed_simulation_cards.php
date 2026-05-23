<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Card;

echo "🌱 Seeding Advantage Cards for Simulation...\n";

$cards = [
    [
        'id' => 1,
        'name' => 'Giga Bet Card',
        'description' => '+0.01 ETH au Base Bet (Valable 1 session)',
        'effect_type' => 'base_bet_modifier',
        'effect_value' => 0.01,
        'duration_type' => 'sessions',
        'duration_value' => 1,
        'price' => 50.0,
        'currency' => 'SNT',
        'is_active' => true,
    ],
    [
        'id' => 2,
        'name' => 'Giga Multiplier Card',
        'description' => '+0.5 au Multiplicateur cible (Valable 1 session)',
        'effect_type' => 'ceiling_increase',
        'effect_value' => 0.50,
        'duration_type' => 'sessions',
        'duration_value' => 1,
        'price' => 100.0,
        'currency' => 'SNT',
        'is_active' => true,
    ],
    [
        'id' => 3,
        'name' => 'Giga Speed Card',
        'description' => '-50% de Cooldown post-session (Valable 1 session)',
        'effect_type' => 'cooldown_reduction',
        'effect_value' => 0.50,
        'duration_type' => 'sessions',
        'duration_value' => 1,
        'price' => 150.0,
        'currency' => 'SNT',
        'is_active' => true,
    ],
    [
        'id' => 4,
        'name' => 'Giga Hour Speed Card',
        'description' => '-50% de Cooldown (Valable pendant 1 heure)',
        'effect_type' => 'cooldown_reduction',
        'effect_value' => 0.50,
        'duration_type' => 'time',
        'duration_value' => 1, // 1 heure
        'price' => 200.0,
        'currency' => 'SNT',
        'is_active' => true,
    ],
];

foreach ($cards as $cardData) {
    Card::updateOrCreate(['id' => $cardData['id']], $cardData);
    echo "   - Card created: {$cardData['name']} (Effect: {$cardData['effect_type']})\n";
}

echo "✅ Seeding Complete!\n";
