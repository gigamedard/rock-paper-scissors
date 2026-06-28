<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Providers\EventServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // ← AJOUTER LES ROUTES API
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Re-sync blockchain limits when time-based cards expire (prevents on-chain expiry bypass)
        $schedule->command('cards:sync-expired-limits')->everyFiveMinutes();
    })

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
        // SECURITY FIX: Never leak database errors to the frontend, even in debug mode.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Erreur interne de la base de données. Veuillez réessayer plus tard.'], 500);
            }
        });
        $exceptions->render(function (\PDOException $e, \Illuminate\Http\Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Erreur de connexion à la base de données. Veuillez réessayer plus tard.'], 500);
            }
        });
    })
    ->withProviders([
        EventServiceProvider::class, // Register the provider here
    ])
    ->create();
