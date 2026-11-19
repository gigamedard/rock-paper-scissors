<?php

namespace App\Http\Controllers;

use App\Models\WavePortalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WavePortalWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = (string) config('wave.portal_webhook_secret', '');
        if ($secret === '') {
            Log::error('[WavePortalWebhook] secret missing');
            return response()->json(['ok' => false, 'error' => 'webhook secret not configured'], 503);
        }

        $raw = (string) $request->getContent();
        if ($raw === '' || strlen($raw) > 65536) {
            return response()->json(['ok' => false, 'error' => 'invalid body'], 400);
        }

        $timestamp = (string) $request->header('X-Wave-Timestamp', '');
        $nonce = (string) $request->header('X-Wave-Nonce', '');
        $signature = strtolower((string) $request->header('X-Wave-Signature', ''));
        $deviceId = substr((string) $request->header('X-Wave-Device-Id', ''), 0, 100);

        if (! preg_match('/^\d+$/', $timestamp) || abs(time() - (int) $timestamp) > 300 || strlen($nonce) < 16 || $signature === '') {
            return response()->json(['ok' => false, 'error' => 'invalid signature headers'], 400);
        }
        if (! Cache::add('wave_portal_nonce_' . hash('sha256', $nonce), 1, 600)) {
            return response()->json(['ok' => false, 'error' => 'nonce replay'], 409);
        }
        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $raw, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'error' => 'bad signature'], 401);
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) return response()->json(['ok' => false, 'error' => 'invalid json'], 400);

        $tx = trim((string) ($data['transaction_id'] ?? ''));
        $direction = (string) ($data['direction'] ?? '');
        $amount = $data['amount'] ?? null;
        $currency = strtoupper((string) ($data['currency'] ?? 'XOF'));
        $occurredAt = $data['occurred_at'] ?? null;
        if ($tx === '' || strlen($tx) > 100 || ! in_array($direction, ['in', 'out'], true) || ! is_numeric($amount) || (float) $amount <= 0 || $currency !== 'XOF') {
            return response()->json(['ok' => false, 'error' => 'invalid payload'], 422);
        }
        try {
            $occurred = $occurredAt ? Carbon::parse((string) $occurredAt) : null;
        } catch (\Throwable $e) {
            $occurred = null;
        }

        if (WavePortalEvent::where('transaction_id', $tx)->exists()) {
            return response()->json(['ok' => false, 'error' => 'duplicate transaction'], 409);
        }

        $event = WavePortalEvent::create([
            'transaction_id' => $tx,
            'device_id' => $deviceId ?: null,
            'direction' => $direction,
            'amount' => (float) $amount,
            'currency' => $currency,
            'occurred_at' => $occurred,
            'status' => 'received',
            'received_at' => now(),
        ]);

        // ID-first: l'événement est seulement stocké ici. Le rapprochement
        // commande ↔ transaction se fait quand le client fournit l'ID.
        return response()->json(['ok' => true, 'status' => $event->fresh()->status]);
    }
}
