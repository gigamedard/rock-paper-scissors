<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GameSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class GameSettingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only attempt to load settings if the database tables exist
        if (app()->runningInConsole() || !Schema::hasTable('game_settings')) {
            return;
        }

        try {
            $settings = GameSetting::all();
            foreach ($settings as $setting) {
                // If the key exists in our 'game_settings' config, override it dynamically:
                Config::set('game_settings.' . $setting->key, GameSetting::getValue($setting->key));
            }
        } catch (\Exception $e) {
            // Ignore errors in case of connection failure during boot
        }
    }
}
