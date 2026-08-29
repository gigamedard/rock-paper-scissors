<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed les cartes du shop (cooldown reduction, ceiling, base bet).
 * Idempotent : updateOrCreate par (name).
 * L'adresse / prix / effet alignés avec le frontend marketplace.js
 * (paiement vers PAYOUT_OPERATOR) et ShopController::activateCard.
 */
class CardSeeder extends Seeder
{
    public function run(): void
    {
        $cards = [
            [
                'name' => 'super sayen',
                'description' => 'Réduit le cooldown actif de 1000 minutes (utilisable 5 sessions). Effet rétroactif à l\'activation.',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 1000,
                'duration_type' => 'sessions',
                'duration_value' => 5,
                'price' => 0.5,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'ki boost',
                'description' => 'Réduit le cooldown actif de 50%. Utilisable 3 sessions.',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 0.5,
                'duration_type' => 'sessions',
                'duration_value' => 3,
                'price' => 0.3,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'ceiling up',
                'description' => 'Augmente le plafond de gains de la session. Utilisable 2 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 2.0,
                'duration_type' => 'sessions',
                'duration_value' => 2,
                'price' => 0.4,
                'currency' => 'SNT',
                'is_active' => true,
            ],
        ];

        foreach ($cards as $card) {
            DB::table('cards')->updateOrInsert(
                ['name' => $card['name']],
                $card
            );
        }

        $this->command->info('✅ ' . count($cards) . ' cartes seedées (super sayen, ki boost, ceiling up).');
    }
}