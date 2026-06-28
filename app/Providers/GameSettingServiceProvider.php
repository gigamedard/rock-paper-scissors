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
        // Resilient boot: if DB is not ready, skip gracefully
        try {
            if (app()->runningInConsole() || !Schema::hasTable('game_settings')) {
                return;
            }
            $settings = GameSetting::all();
            foreach ($settings as $setting) {
                Config::set('game_settings.' . $setting->key, GameSetting::getValue($setting->key));
            }
        } catch (\Exception $e) {
            // DB not ready yet — app will boot without game settings.
            // They will be loaded on the next request once DB is available.
        }
    }
}
