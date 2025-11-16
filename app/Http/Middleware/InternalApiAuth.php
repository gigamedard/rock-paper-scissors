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
        Log::info('InternalApiAuth middleware processing request');
        Log::info('Request headers: ' . json_encode($request->headers->all()));

        $secret = config('app.internal_api_secret');

        if (!$secret || $request->header('X-Internal-Secret') !== $secret) {
            Log::warning('Internal API authentication failed');
            abort(403, 'Unauthorized action.');
        }

        Log::info('Internal API authentication successful');
        return $next($request);
    }
}
