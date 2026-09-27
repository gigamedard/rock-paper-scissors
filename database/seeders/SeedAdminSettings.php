<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameSetting;

class SeedAdminSettings extends Seeder
{
    /**
     * Run the database seeds.
     * Source of truth for ALL GameSettings parameters.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'security_coefficient',
                'value' => '1000',
                'type' => 'integer',
                'group' => 'blockchain',
                'description' => 'Coefficient multiplicateur pour le dépôt de sécurité (Base Bet x Coef)'
            ],
            [
                'key' => 'house_fee_percent',
                'value' => '2.5',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Pourcentage de frais affichés (référence dashboard)'
            ],
            [
                'key' => 'smart_contract_fee_percentage',
                'value' => '2.5',
                'type' => 'float',
                'group' => 'blockchain',
                'description' => 'Frais de transaction Smart Contract (%) — doit correspondre à feeBasisPoints/100'
            ],
            [
                'key' => 'game_fee_percentage',
                'value' => '5.0',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Frais de jeu globaux (%) — frais perçus par la house sur les gains'
            ],
            [
                'key' => 'max_bot_per_pool',
                'value' => '10',
                'type' => 'integer',
                'group' => 'simulation',
                'description' => 'Nombre maximum de bots autorisés par pool de combat'
            ],
            [
                'key' => 'martingale_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'gameplay',
                'description' => 'Activer ou désactiver le doublement de mise automatique après une défaite'
            ],
            [
                'key' => 'min_bet_eth',
                'value' => '0.0004',
                'type' => 'float',
                'group' => 'gameplay',
                'description' => 'Mise minimale autorisée (palier 1 ≈ 2000 XOF)'
            ],
            [
                'key' => 'max_martingale_level',
                'value' => '4',
                'type' => 'integer',
                'group' => 'gameplay',
                'description' => 'Nombre maximum de doublements avant réinitialisation de la mise'
            ],
            [
                'key' => 'max_q_base',
                'value' => '1.2',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Plafond target_q par défaut (joueur sans condition)'
            ],
            [
                'key' => 'max_q_referral',
                'value' => '2.0',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Plafond target_q débloqué avec le nombre requis de filleuls validés'
            ],
            [
                'key' => 'max_q_hard',
                'value' => '3.0',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Plafond absolu target_q atteignable via cartes ceiling_increase'
            ],
            [
                'key' => 'max_q_special',
                'value' => '4.0',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Plafond target_q pour cas spéciaux (carte q_special_unlock)'
            ],
            [
                'key' => 'referrals_required_for_max',
                'value' => '2',
                'type' => 'integer',
                'group' => 'economy',
                'description' => 'Nombre de filleuls validés requis pour débloquer le plafond target_q referral'
            ],
        ];

        foreach ($settings as $setting) {
            GameSetting::updateOrCreate(['key' => $setting['key']], $setting);
            \Illuminate\Support\Facades\Cache::forget('game_setting_' . $setting['key']);
        }
    }
}
