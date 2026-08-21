<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthInternalApi
{
    /**
     * Gère une requête entrante pour l'API interne.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedSecret = $request->header('X-Internal-Secret');
        $expectedSecret = config('app.internal_api_secret');

        if (!$providedSecret || !$expectedSecret || !hash_equals($expectedSecret, $providedSecret)) {
            // Si le secret est manquant ou invalide, on retourne une erreur 403.
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}