<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameSetting;

class GameSettingSeeder extends Seeder
{
    public function run(): void
    {
        GameSetting::setValue(
            'security_coefficient', 
            1000, 
            'integer', 
            'blockchain', 
            'Coefficient de sécurité multiplicateur pour la mise de base (Stake = bet * security_coefficient)'
        );
        
        GameSetting::setValue(
            'game_fee_percentage',
            5.0,
            'float',
            'business',
            'Frais de jeu globaux (%)'
        );

        GameSetting::setValue(
            'smart_contract_fee_percentage',
            2.5,
            'float',
            'blockchain',
            'Frais de transaction Smart Contract (%)'
        );
    }
}
