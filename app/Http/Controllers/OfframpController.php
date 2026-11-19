<?php

namespace App\Http\Controllers;

use App\Models\OfframpOrder;
use App\Services\OfframpService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class OfframpController extends Controller
{
    public function __construct(
        private OfframpService $offramp,
        private TelegramService $telegram
    ) {
    }

    public function adminOrders(Request $request)
    {
        $status = $request->query('status');
        $orders = $this->offramp->ordersForAdmin($status ? (string) $status : null, 100)
            ->map(fn (OfframpOrder $o) => $this->serialize($o));
        return response()->json(['orders' => $orders]);
    }

    public function approve(Request $request, OfframpOrder $order)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $order = $this->offramp->approve($order, (int) $request->user()->id, $data['note'] ?? null);
        $this->notifyUser($order, "✅ Off-ramp {$order->reference} validé. Paiement USDT en cours.");
        return response()->json(['order' => $this->serialize($order)]);
    }

    public function reject(Request $request, OfframpOrder $order)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $order = $this->offramp->reject($order, (int) $request->user()->id, $data['reason'] ?? 'Refusée');
        $this->notifyUser($order, "❌ Off-ramp {$order->reference} refusé. Motif : " . ($data['reason'] ?? 'Refusée'));
        return response()->json(['order' => $this->serialize($order)]);
    }

    private function notifyUser(OfframpOrder $order, string $message): void
    {
        if ($order->channel === 'telegram' && $order->chat_id) {
            $this->telegram->sendMessage((string) $order->chat_id, $message);
        }
    }

    private function serialize(OfframpOrder $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'user_id' => $order->user_id,
            'wallet' => $order->user?->wallet_address,
            'chat_id' => $order->chat_id,
            'payout_network' => $order->payout_network,
            'payout_address' => $order->payout_address,
            'avaxe_amount' => (float) $order->avaxe_amount,
            'usdt_amount' => (float) $order->usdt_amount,
            'rate' => (float) $order->rate,
            'fee_percent' => (float) $order->fee_percent,
            'status' => $order->status,
            'deposit_address' => $order->deposit_address,
            'deposit_amount' => $order->deposit_amount !== null ? (float) $order->deposit_amount : null,
            'tx_hash' => $order->tx_hash,
            'payout_tx_hash' => $order->payout_tx_hash,
            'sweep_status' => $order->sweep_status,
            'sweep_tx_hash' => $order->sweep_tx_hash,
            'admin_note' => $order->admin_note,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }
}
