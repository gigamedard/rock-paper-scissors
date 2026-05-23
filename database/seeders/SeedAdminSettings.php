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
                'value' => '100',
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
                'value' => '0.01',
                'type' => 'float',
                'group' => 'gameplay',
                'description' => 'Mise minimale autorisée en ETH (base_bet)'
            ],
            [
                'key' => 'max_martingale_level',
                'value' => '4',
                'type' => 'integer',
                'group' => 'gameplay',
                'description' => 'Nombre maximum de doublements avant réinitialisation de la mise'
            ],
        ];

        foreach ($settings as $setting) {
            GameSetting::updateOrCreate(['key' => $setting['key']], $setting);
            \Illuminate\Support\Facades\Cache::forget('game_setting_' . $setting['key']);
        }
    }
}
