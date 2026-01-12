<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class InternalApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$secret || !$providedSecret || !hash_equals($secret, $providedSecret)) {
            Log::warning('Internal API authentication failed', ['ip' => $request->ip()]);
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
