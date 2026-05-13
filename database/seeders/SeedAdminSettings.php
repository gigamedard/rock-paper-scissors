<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameSetting;

class SeedAdminSettings extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'security_coefficient',
                'value' => '1000',
                'type' => 'integer',
                'group' => 'general',
                'description' => 'Coefficient multiplicateur pour le dépôt de sécurité (Base Bet x Coef)'
            ],
            [
                'key' => 'house_fee_percent',
                'value' => '2.5',
                'type' => 'float',
                'group' => 'economy',
                'description' => 'Pourcentage de frais prélevés sur les dépôts'
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
                'description' => 'Mise minimale autorisée en ETH'
            ]
        ];

        foreach ($settings as $setting) {
            GameSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
