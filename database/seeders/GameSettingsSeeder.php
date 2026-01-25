<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameSetting;

class GameSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Tokenomics
        GameSetting::setValue('game_fee_percentage', '5', 'float', 'tokenomics', 'Percentage fee taken from each pot');
        GameSetting::setValue('min_bet_amount', '0.1', 'float', 'tokenomics', 'Minimum bet amount in AVAX');
        
        // Game Parameters
        GameSetting::setValue('pool_size_small', '2', 'integer', 'game', 'Number of players in a small pool');
        GameSetting::setValue('pool_size_medium', '10', 'integer', 'game', 'Number of players in a medium pool');
        GameSetting::setValue('pool_size_large', '100', 'integer', 'game', 'Number of players in a large pool');
        GameSetting::setValue('turn_timeout_seconds', '30', 'integer', 'game', 'Seconds allowed for a move');
        
        // System
        GameSetting::setValue('maintenance_mode', '0', 'boolean', 'system', 'Put game in maintenance mode');
        GameSetting::setValue('allow_new_registrations', '1', 'boolean', 'system', 'Allow new users to register');
    }
}
