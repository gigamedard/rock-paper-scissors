<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // Routes API (prefixées automatiquement par /api)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Routes Web
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Routes Auth (optionnel, si tu veux garder un fichier séparé)
            if (file_exists(base_path('routes/auth.php'))) {
                Route::middleware('web')
                    ->prefix('auth')
                    ->group(base_path('routes/auth.php'));
            }
        });
    }
}
