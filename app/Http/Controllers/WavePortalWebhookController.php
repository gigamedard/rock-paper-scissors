<?php

namespace App\Http\Controllers;

use App\Models\WaveOrder;
use App\Models\WavePortalEvent;
use App\Services\WaveTelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WavePortalWebhookController extends Controller
{
    public function __construct(private WaveTelegramService $telegram)
    {
    }

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

        try {
            $this->match($event);
        } catch (\Throwable $e) {
            $event->update(['status' => 'error', 'error' => substr($e->getMessage(), 0, 250)]);
            Log::warning('[WavePortalWebhook] match failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true, 'status' => $event->fresh()->status]);
    }

    private function match(WavePortalEvent $event): void
    {
        $amount = (float) $event->amount;
        if ($event->direction === 'in') {
            $orders = WaveOrder::where('direction', WaveOrder::DIRECTION_BUY_USDT)
                ->where('status', WaveOrder::STATUS_PENDING_PAYMENT)
                ->where('fiat_amount', $amount)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->get();

            if ($orders->count() === 1) {
                $order = $orders->first();
                $auto = (bool) config('wave.auto_payout_enabled', true)
                    && (float) $order->usdt_amount <= (float) config('wave.auto_payout_max_usdt', 10)
                    && ! empty($order->mobileUser?->payout_address);
                $order->update([
                    'status' => $auto ? WaveOrder::STATUS_APPROVED : WaveOrder::STATUS_PENDING_REVIEW,
                    'payment_ref' => $event->transaction_id,
                    'verified_at' => now(),
                    'admin_note' => $auto ? 'Paiement Wave portail: payout USDT auto' : 'Paiement Wave détecté via portail',
                ]);
                $event->update([
                    'status' => $auto ? 'matched_auto_payout' : 'matched_pending_review',
                    'matched_order_id' => $order->id,
                ]);
                $message = $auto
                    ? "💳 Paiement Wave détecté pour {$order->reference}. Envoi USDT automatique en cours."
                    : "💳 Paiement Wave détecté pour {$order->reference}. Vérification admin en cours.";
                $this->notifyOrder($order, $message);
                $this->telegram->sendAdmin("💳 Paiement Wave entrant\n{$event->transaction_id}\n{$event->amount} XOF\nCommande: {$order->reference}\nStatut: " . ($auto ? 'auto payout USDT' : 'pending_review'));
                return;
            }

            $status = $orders->count() > 1 ? 'ambiguous' : 'unmatched';
            $event->update(['status' => $status]);
            $this->telegram->sendAdmin("⚠️ Paiement Wave {$status}\n{$event->transaction_id}\n{$event->amount} XOF\nAucune commande unique correspondante.");
            return;
        }

        // Sortie Wave : ne jamais marquer payé automatiquement. On trace pour
        // que l’admin confirme le payout mobile money.
        $event->update(['status' => 'outgoing_logged']);
        $this->telegram->sendAdmin("↔️ Sortie Wave détectée\n{$event->transaction_id}\n{$event->amount} XOF\nÀ rapprocher manuellement.");
    }

    private function notifyOrder(WaveOrder $order, string $message): void
    {
        if ($order->chat_id) {
            $this->telegram->sendMessage((string) $order->chat_id, $message);
        }
    }
}
