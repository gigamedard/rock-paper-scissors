<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PreMove;
use App\Observers\PreMoveObserver;
use Illuminate\Support\Facades\Auth;
use App\Models\ApiToken;
use App\Services\ApiTokenGuard;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }



    public function boot()
    {   
        PreMove::observe(PreMoveObserver::class);
        Auth::extend('api-token', function ($app, $name, array $config) {
            return new ApiTokenGuard($config['provider']);
        });
    }

}
