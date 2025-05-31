<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PreMove;
use App\Observers\PreMoveObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PreMove::observe(PreMoveObserver::class);
    }
}
