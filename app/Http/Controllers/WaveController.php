<?php

namespace App\Http\Controllers;

use App\Models\WaveOrder;
use App\Services\TelegramService;
use App\Services\WaveService;
use Illuminate\Http\Request;

class WaveController extends Controller
{
    public function __construct(
        private WaveService $wave,
        private TelegramService $telegram
    ) {
    }

    public function adminOrders(Request $request)
    {
        $status = $request->query('status');
        $orders = $this->wave->ordersForAdmin($status ? (string) $status : null, 100)
            ->map(fn (WaveOrder $o) => $this->serialize($o));
        return response()->json(['orders' => $orders]);
    }

    public function markPaid(Request $request, WaveOrder $order)
    {
        $data = $request->validate([
            'usdt_payout_tx_hash' => 'nullable|string|max:100',
            'fiat_tx_ref' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:1000',
        ]);
        $order = $this->wave->markPaid(
            $order,
            (int) $request->user()->id,
            $data['usdt_payout_tx_hash'] ?? null,
            $data['fiat_tx_ref'] ?? null,
            $data['note'] ?? null
        );
        $this->notifyUser($order, "✅ Commande Wave {$order->reference} payée.");
        return response()->json(['order' => $this->serialize($order)]);
    }

    public function reject(Request $request, WaveOrder $order)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $order = $this->wave->reject($order, (int) $request->user()->id, $data['reason'] ?? 'Refusée');
        $this->notifyUser($order, "❌ Commande Wave {$order->reference} refusée. Motif : " . ($data['reason'] ?? 'Refusée'));
        return response()->json(['order' => $this->serialize($order)]);
    }

    private function notifyUser(WaveOrder $order, string $message): void
    {
        if ($order->channel === 'telegram' && $order->chat_id) {
            $this->telegram->sendMessage((string) $order->chat_id, $message);
        }
    }

    private function serialize(WaveOrder $o): array
    {
        return [
            'id' => $o->id, 'reference' => $o->reference, 'user_id' => $o->user_id,
            'wallet' => $o->user?->wallet_address, 'chat_id' => $o->chat_id,
            'direction' => $o->direction, 'operator' => $o->operator, 'country' => $o->country,
            'currency' => $o->currency, 'fiat_amount' => (float) $o->fiat_amount,
            'usdt_amount' => (float) $o->usdt_amount, 'usdt_gross' => $o->usdt_gross !== null ? (float) $o->usdt_gross : null,
            'rate' => (float) $o->rate, 'fee_percent' => (float) $o->fee_percent, 'status' => $o->status,
            'payment_phone' => $o->payment_phone, 'payout_phone' => $o->payout_phone,
            'payout_network' => $o->payout_network, 'deposit_address' => $o->deposit_address,
            'payment_ref' => $o->payment_ref, 'usdt_deposit_tx_hash' => $o->usdt_deposit_tx_hash,
            'usdt_payout_tx_hash' => $o->usdt_payout_tx_hash, 'fiat_tx_ref' => $o->fiat_tx_ref,
            'admin_note' => $o->admin_note, 'created_at' => $o->created_at?->toIso8601String(),
            'paid_at' => $o->paid_at?->toIso8601String(),
        ];
    }
}
