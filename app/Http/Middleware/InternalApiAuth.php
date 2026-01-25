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
        $secret = config('app.INTERNAL_API_SECRET');
        $providedSecret = $request->header('X-Internal-Secret');

        // Check if secret is configured
        if (empty($secret)) {
            Log::error('INTERNAL_API_SECRET is not configured in .env');
            abort(500, 'Server configuration error');
        }

        if (!$providedSecret || !hash_equals($secret, $providedSecret)) {
            Log::warning('Internal API authentication failed', ['ip' => $request->ip()]);
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
