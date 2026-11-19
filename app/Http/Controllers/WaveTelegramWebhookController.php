<?php

namespace App\Http\Controllers;

use App\Services\WaveTelegramBot;
use App\Services\WaveTelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WaveTelegramWebhookController extends Controller
{
    public function handle(Request $request, WaveTelegramBot $bot, WaveTelegramService $telegram)
    {
        if (! $telegram->enabled()) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }
        $secret = (string) config('wave.telegram.webhook_secret', '');
        if ($secret === '') {
            Log::error('[WaveWebhook] WAVE_TELEGRAM_WEBHOOK_SECRET manquant');
            return response()->json(['ok' => false, 'error' => 'webhook secret not configured'], 503);
        }
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (! hash_equals($secret, $given)) {
            return response()->json(['ok' => false], 403);
        }
        $update = $request->json()->all();
        if (! is_array($update) || $update === []) return response()->json(['ok' => true]);
        $bot->handle($update);
        return response()->json(['ok' => true]);
    }
}
