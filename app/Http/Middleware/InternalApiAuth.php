<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // On récupère la clé secrète depuis les fichiers de configuration
        $secret = config('app.internal_api_secret');

        // On vérifie que la clé est bien configurée ET que l'en-tête de la requête correspond
        if (!$secret || $request->header('X-Internal-Secret') !== $secret) {
            // Si la clé est manquante ou incorrecte, on bloque la requête.
            abort(403, 'Unauthorized action.');
        }

        // Si tout est bon, on laisse la requête continuer.
        return $next($request);
    }
}