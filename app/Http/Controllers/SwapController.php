<?php

namespace App\Http\Controllers;

use App\Models\SwapOrder;
use App\Services\SwapService;
use Illuminate\Http\Request;

class SwapController extends Controller
{
    public function __construct(private SwapService $swap) {}

    public function config() { return response()->json($this->swap->config()); }

    public function create(Request $request)
    {
        $data = $request->validate([
            'direction' => 'required|in:usdt_to_avaxe,avaxe_to_usdt',
            'network' => 'required|in:polygon,bsc',
            'amount' => 'required|numeric|min:0.00000001',
            'payout_address' => 'required|string|max:42',
        ]);
        $order = $data['direction'] === SwapOrder::USDT_TO_AVAXE
            ? $this->swap->createUsdtToAvaxe($data['network'], (float) $data['amount'], $data['payout_address'])
            : $this->swap->createAvaxeToUsdt($data['network'], (float) $data['amount'], $data['payout_address']);
        return response()->json(['order' => $this->serialize($order)], 201);
    }

    public function show(string $reference)
    {
        $order = $this->swap->find($reference);
        if (! $order) return response()->json(['error' => 'not_found'], 404);
        return response()->json(['order' => $this->serialize($order)]);
    }

    public function submitTx(Request $request, string $reference)
    {
        $data = $request->validate(['tx_hash' => 'required|string|max:100']);
        $order = $this->swap->find($reference);
        if (! $order) return response()->json(['error' => 'not_found'], 404);
        $order = $this->swap->submitDeposit($order, $data['tx_hash']);
        return response()->json(['order' => $this->serialize($order)]);
    }

    private function serialize(SwapOrder $o): array
    {
        return [
            'reference' => $o->reference, 'direction' => $o->direction, 'network' => $o->network,
            'amount_in' => (float) $o->amount_in, 'amount_out' => (float) $o->amount_out, 'rate' => (float) $o->rate,
            'deposit_address' => $o->deposit_address, 'payout_address' => $o->payout_address,
            'status' => $o->status, 'deposit_tx_hash' => $o->deposit_tx_hash,
            'payout_tx_hash' => $o->payout_tx_hash, 'confirmations' => (int) $o->confirmations,
            'error' => $o->error, 'created_at' => $o->created_at?->toIso8601String(),
            'completed_at' => $o->completed_at?->toIso8601String(),
        ];
    }
}
