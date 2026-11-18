<?php

namespace App\Http\Controllers;

use App\Models\OnrampOrder;
use App\Models\User;
use App\Services\OnrampService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class OnrampController extends Controller
{
    public function __construct(
        private OnrampService $onramp,
        private TelegramService $telegram
    ) {
    }

    public function linkCode(Request $request)
    {
        return response()->json($this->onramp->issueLinkCode($request->user()));
    }

    public function config(Request $request)
    {
        $networks = [];
        foreach ((array) config('onramp.networks', []) as $key => $network) {
            $networks[] = [
                'key' => $key,
                'label' => $network['label'] ?? $key,
                'configured' => trim((string) ($network['address'] ?? '')) !== '',
            ];
        }
        $packages = array_values($this->onramp->packages());

        return response()->json([
            'enabled' => (bool) config('onramp.enabled', false),
            'secret_editor_url' => $request->user()?->is_admin ? (string) config('onramp.secret_editor_url', '') : null,
            'config_editor_url' => $request->user()?->is_admin ? (string) config('onramp.config_editor_url', '') : null,
            'terms_version' => (string) config('onramp.terms_version', 'v1'),
            'terms_url' => (string) config('onramp.terms_url', ''),
            'daily_limit_usdt' => (float) config('onramp.daily_limit_usdt', 100),
            'max_order_usdt' => (float) config('onramp.max_order_usdt', 50),
            'networks' => $networks,
            'packages' => $packages,
        ]);
    }

    public function orders(Request $request)
    {
        $orders = $this->onramp->ordersForUser($request->user(), 20)->map(fn (OnrampOrder $o) => $this->serialize($o));
        return response()->json(['orders' => $orders]);
    }

    public function adminOrders(Request $request)
    {
        $status = $request->query('status');
        $orders = $this->onramp->ordersForAdmin($status ? (string) $status : null, 100)
            ->map(fn (OnrampOrder $o) => $this->serialize($o, true));
        return response()->json(['orders' => $orders]);
    }

    public function adminUsers(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $query = User::query()
            ->select(['id', 'wallet_address', 'email', 'onramp_allowed', 'is_admin', 'created_at'])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('wallet_address', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        return response()->json(['users' => $query->limit(50)->get()]);
    }

    public function approve(Request $request, OnrampOrder $order)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $order = $this->onramp->approve($order, (int) $request->user()->id, $data['note'] ?? null);
        $this->notifyUser($order, "✅ Paiement validé pour {$order->reference}. Le crédit AVAXe est en cours.");
        return response()->json(['order' => $this->serialize($order, true)]);
    }

    public function reject(Request $request, OnrampOrder $order)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $order = $this->onramp->reject($order, (int) $request->user()->id, $data['reason'] ?? 'Refusée');
        $this->notifyUser($order, "❌ Commande {$order->reference} refusée. Motif : " . ($data['reason'] ?? 'Refusée'));
        return response()->json(['order' => $this->serialize($order, true)]);
    }

    public function credit(Request $request, OnrampOrder $order)
    {
        $data = $request->validate(['credit_tx_hash' => 'required|string|max:100']);
        $order = $this->onramp->credit($order, $data['credit_tx_hash'], (int) $request->user()->id);
        $this->notifyUser($order, "✅ AVAXe crédités pour {$order->reference}. Tx : {$order->credit_tx_hash}");
        return response()->json(['order' => $this->serialize($order, true)]);
    }

    public function allowUser(Request $request, User $user)
    {
        $data = $request->validate(['allowed' => 'required|boolean']);
        $user = $this->onramp->allowUser($user, (bool) $data['allowed']);
        return response()->json(['user_id' => $user->id, 'onramp_allowed' => $user->onramp_allowed]);
    }

    private function notifyUser(OnrampOrder $order, string $message): void
    {
        if ($order->channel === 'telegram' && $order->chat_id) {
            $this->telegram->sendMessage((string) $order->chat_id, $message);
        }
    }

    private function serialize(OnrampOrder $order, bool $admin = false): array
    {
        $data = [
            'id' => $order->id,
            'reference' => $order->reference,
            'network' => $order->network,
            'package_code' => $order->package_code,
            'usdt_amount' => (float) $order->usdt_amount,
            'avaxe_amount' => (float) $order->avaxe_amount,
            'status' => $order->status,
            'tx_hash' => $order->tx_hash,
            'credit_tx_hash' => $order->credit_tx_hash,
            'created_at' => $order->created_at?->toIso8601String(),
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'approved_at' => $order->approved_at?->toIso8601String(),
            'credited_at' => $order->credited_at?->toIso8601String(),
        ];
        if ($admin) {
            $data['user_id'] = $order->user_id;
            $data['chat_id'] = $order->chat_id;
            $data['wallet'] = $order->user?->wallet_address;
            $data['onramp_allowed'] = (bool) $order->user?->onramp_allowed;
            $data['admin_note'] = $order->admin_note;
        }
        return $data;
    }
}
