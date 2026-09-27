<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed le catalogue de cartes du shop (thème Dragon Ball).
 *
 * Toutes les cartes sont visibles/achetables par TOUS les paliers :
 *   - Un joueur palier 1 voit les cartes palier 2 (objectif).
 *   - Un joueur palier 2 peut acheter un pass palier 1 pour gratter des minutes.
 *
 * Effets supportés :
 *   - cooldown_reduction : effect_value = MINUTES retirées (positif > 1)
 *   - ceiling_increase   : effect_value = DELTA ajouté au plafond target_q
 *   - q_special_unlock   : débloque le plafond spécial (jusqu'à 4.0)
 *
 * Idempotent : updateOrInsert par (name).
 * Devise : SNT.
 * Prix de référence : 1 PRANA = 100 XOF. Palier 2 = ×380 du palier 1.
 */
class CardSeeder extends Seeder
{
    public function run(): void
    {
        $cards = [
            // -----------------------------------------------------------------
            // PASSES COOLDOWN — PALIER 1 (base 4320 min -> 480 min : -3840 min)
            // -----------------------------------------------------------------
            [
                'name' => 'Kaioken',
                'description' => 'Réduit le cooldown de 3840 minutes (3 jours -> 8 heures). 3 sessions.',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 3840,
                'duration_type' => 'sessions',
                'duration_value' => 3,
                'price' => 20,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Kaioken x10',
                'description' => 'Réduit le cooldown de 3840 minutes. 10 sessions (remise).',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 3840,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 50,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Kaioken x20',
                'description' => 'Réduit le cooldown de 3840 minutes. 25 sessions (remise max).',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 3840,
                'duration_type' => 'sessions',
                'duration_value' => 25,
                'price' => 100,
                'currency' => 'SNT',
                'is_active' => true,
            ],

            // -----------------------------------------------------------------
            // PASSES COOLDOWN — PALIER 2 (base 30240 min -> 4320 min : -25920 min)
            // -----------------------------------------------------------------
            [
                'name' => 'Instant Transmission',
                'description' => 'Réduit le cooldown de 25920 minutes (3 semaines -> 3 jours). 3 sessions.',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 25920,
                'duration_type' => 'sessions',
                'duration_value' => 3,
                'price' => 7600,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Instant Transmission x10',
                'description' => 'Réduit le cooldown de 25920 minutes. 10 sessions (remise).',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 25920,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 19000,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Instant Transmission x100',
                'description' => 'Réduit le cooldown de 25920 minutes. 25 sessions (remise max).',
                'effect_type' => 'cooldown_reduction',
                'effect_value' => 25920,
                'duration_type' => 'sessions',
                'duration_value' => 25,
                'price' => 38000,
                'currency' => 'SNT',
                'is_active' => true,
            ],

            // -----------------------------------------------------------------
            // CEILINGS — PALIER 1 (+0.02 par carte, 20 sessions)
            // -----------------------------------------------------------------
            [
                'name' => 'Super Saiyan',
                'description' => 'Augmente le plafond target_q de +0.02. 20 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.02,
                'duration_type' => 'sessions',
                'duration_value' => 20,
                'price' => 10,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Super Saiyan 2',
                'description' => 'Augmente le plafond target_q de +0.10. 20 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.10,
                'duration_type' => 'sessions',
                'duration_value' => 20,
                'price' => 50,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Super Saiyan God',
                'description' => 'Augmente le plafond target_q de +0.30. 20 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.30,
                'duration_type' => 'sessions',
                'duration_value' => 20,
                'price' => 150,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Super Saiyan Blue',
                'description' => 'Augmente le plafond target_q de +0.60. 20 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.60,
                'duration_type' => 'sessions',
                'duration_value' => 20,
                'price' => 300,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Super Saiyan Blue Evolved',
                'description' => 'Augmente le plafond target_q de +1.00. 20 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 1.00,
                'duration_type' => 'sessions',
                'duration_value' => 20,
                'price' => 500,
                'currency' => 'SNT',
                'is_active' => true,
            ],

            // -----------------------------------------------------------------
            // CEILINGS — PALIER 2 (+0.02 / +0.10 / +0.30 / +0.60 / +1.00, 10 sessions, ×380)
            // -----------------------------------------------------------------
            [
                'name' => 'Super Saiyan Rosé',
                'description' => 'Augmente le plafond target_q de +0.02. 10 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.02,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 3800,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Super Saiyan Blue Kaioken',
                'description' => 'Augmente le plafond target_q de +0.10. 10 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.10,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 19000,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Ultra Ego',
                'description' => 'Augmente le plafond target_q de +0.30. 10 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.30,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 57000,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Gohan Beast',
                'description' => 'Augmente le plafond target_q de +0.60. 10 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 0.60,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 114000,
                'currency' => 'SNT',
                'is_active' => true,
            ],
            [
                'name' => 'Mastered Ultra Instinct',
                'description' => 'Augmente le plafond target_q de +1.00. 10 sessions.',
                'effect_type' => 'ceiling_increase',
                'effect_value' => 1.00,
                'duration_type' => 'sessions',
                'duration_value' => 10,
                'price' => 190000,
                'currency' => 'SNT',
                'is_active' => true,
            ],

            // -----------------------------------------------------------------
            // CAS SPÉCIAL — déblocage du plafond jusqu'à 4.0
            // -----------------------------------------------------------------
            [
                'name' => 'Super Saiyan 4',
                'description' => 'Cas spécial : débloque le plafond target_q jusqu\'à 4.0. 1 session.',
                'effect_type' => 'q_special_unlock',
                'effect_value' => 0,
                'duration_type' => 'sessions',
                'duration_value' => 1,
                'price' => 5000,
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

        // Mise en veille des cartes legacy (on ne supprime pas : user_cards référence card_id).
        $legacy = ['super sayen', 'ki boost', 'ceiling up', 'kundalini seal', 'Base Bet Boost', 'Target Q Boost'];
        DB::table('cards')->whereIn('name', $legacy)->update(['is_active' => false]);

        $this->command->info('✅ ' . count($cards) . ' cartes Dragon Ball seedées (passes cooldown + ceilings + cas spécial).');
    }
}
