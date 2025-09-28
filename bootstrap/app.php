<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Providers\EventServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // ← AJOUTER LES ROUTES API
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
<<<<<<< HEAD
        // Registered a custom middleware alias: 'token.auth'
        // This is a clearer name and avoids conflicts with Laravel's internal 'auth:api'.
        $middleware->alias([
            'token.auth' => \App\Http\Middleware\ApiAuth::class,
=======
        // ← AJOUTER SANCTUM MIDDLEWARE POUR LES API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
>>>>>>> a8bc1c0ea052960d97d8de70c0451a9e3b25884a
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        EventServiceProvider::class, // Register the provider here
    ])
    ->create();
