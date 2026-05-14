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
        // Registered a custom middleware alias: 'token.auth'
        // This is a clearer name and avoids conflicts with Laravel's internal 'auth:api'.
        $middleware->alias([
            'token.auth' => \App\Http\Middleware\ApiAuth::class,
            'auth.internal' => \App\Http\Middleware\InternalApiAuth::class,
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '*', 
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        EventServiceProvider::class, // Register the provider here
    ])
    ->create();
