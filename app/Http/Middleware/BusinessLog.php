<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Log;
use Closure;

/**
 * JOURNAL MÉTIER (2026-10-06) — trace les requêtes/réponses des endpoints
 * métier (pool, marketplace, claim, shop) avec résultat et durée.
 *
 * Objectif : corréler le journal visuel frontend 🐞 avec le serveur quand un
 * utilisateur (bêta) rapporte « erreur » sur rejoindre une pool / marketplace.
 *
 * Volontairement léger : log UNIQUEMENT les échecs (>=400) + les succès des
 * endpoints clés dans staging (le volume succès reste faible en bêta).
 */
class BusinessLog
{
    /** Endpoints métier tracés en succès (préfixes). */
    private const WATCH_SUCCESS = [
        'user/pre-moves', 'user/status', 'ipfs/upload', 'marketplace/trades',
        'marketplace/stats', 'shop/buy', 'shop/activate', 'wallet/generate',
        'wallet/verify', 'claim', 'pool',
    ];

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        try {
            $path = $request->path();          // ex: api/user/pre-moves
            $status = $response->getStatusCode();
            $isBusiness = false;
            foreach (self::WATCH_SUCCESS as $prefix) {
                if (str_contains($path, $prefix)) { $isBusiness = true; break; }
            }
            if (!$isBusiness) {
                return $response;
            }

            $userId = optional($request->user())->id;
            $durMs = (int) ((microtime(true) - LARAVEL_START) * 1000);

            if ($status >= 400) {
                $body = substr((string) $response->getContent(), 0, 400);
                Log::channel('business')->warning('API_FAIL', [
                    'status' => $status, 'method' => $request->getMethod(),
                    'path' => $path, 'user' => $userId, 'dur_ms' => $durMs,
                    'body' => $body,
                ]);
            } elseif (app()->environment('staging', 'local')) {
                // Succès tracés en staging seulement (bêta) — utile pour
                // suivre les flux utilisateur dans le journal serveur.
                $body = substr((string) $response->getContent(), 0, 200);
                Log::channel('business')->info('API_OK', [
                    'status' => $status, 'method' => $request->getMethod(),
                    'path' => $path, 'user' => $userId, 'dur_ms' => $durMs,
                    'body' => $body,
                ]);
            }
        } catch (\Throwable $e) {
            // jamais bloquant
        }

        return $response;
    }
}