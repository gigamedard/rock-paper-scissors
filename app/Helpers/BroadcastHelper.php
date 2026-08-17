<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastHelper
{
    /**
     * Execute a synchronous broadcast dispatch without letting it abort the
     * surrounding business logic. Broadcast failures (e.g. Reverb unreachable)
     * must never abort fight/session/pool processing.
     */
    public static function safe(callable $callback, string $context): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning("[BROADCAST_RESILIENCE] Broadcast failed for {$context}: " . $e->getMessage());
        }
    }
}
