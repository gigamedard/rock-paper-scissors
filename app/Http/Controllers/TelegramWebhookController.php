<?php

namespace App\Http\Controllers;

use App\Services\OnrampTelegramBot;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, OnrampTelegramBot $bot, TelegramService $telegram)
    {
        // Bot non configuré : ne pas accepter de trafic arbitraire.
        if (! $telegram->enabled()) {
            return response()->json(['ok' => true, 'disabled' => true]);
        }

        $secret = (string) config('onramp.telegram.webhook_secret', '');
        if ($secret === '') {
            Log::error('[TelegramWebhook] TELEGRAM_WEBHOOK_SECRET manquant');
            return response()->json(['ok' => false, 'error' => 'webhook secret not configured'], 503);
        }

        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (! hash_equals($secret, $given)) {
            return response()->json(['ok' => false], 403);
        }

        $update = $request->json()->all();
        if (! is_array($update) || $update === []) {
            return response()->json(['ok' => true]);
        }

        $bot->handle($update);
        return response()->json(['ok' => true]);
    }
}
