<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Illuminate\Support\Facades\Log;
use Closure;

class ApiAuth
{
    public function handle($request, Closure $next)
    {
        $header = $request->header('Authorization');

        // Log auth attempts to a dedicated channel to avoid spamming the main laravel.log
        Log::channel('auth_debug')->debug("ApiAuth: " . substr((string)$header, 0, 30) . "...");

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $plain = substr($header, 7);
        $hashed = hash('sha256', $plain);

        $token = ApiToken::where('token', $hashed)->first();

        if (!$token || !$token->isValid()) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        // Attach user to request
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}

