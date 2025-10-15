<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InternalTradeController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'offerId' => 'required|numeric|unique:trades,blockchain_trade_id',
            'seller' => 'required|string',
            'sntAmount' => 'required|numeric',
            'avaxAmount' => 'required|numeric',
            'expiresAt' => 'required|numeric',
        ]);

        Log::info('Listener a rapporté une nouvelle offre', $validated);

        Trade::create([
            'blockchain_trade_id' => $validated['offerId'],
            'seller_wallet_address' => $validated['seller'],
            'snt_amount' => $validated['sntAmount'],
            'avax_amount' => $validated['avaxAmount'],
            'status' => 'open',
            'expires_at' => Carbon::createFromTimestamp($validated['expiresAt']),
        ]);

        return response()->json(['status' => 'success'], 201);
    }
    
    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'offerId' => 'required|numeric|exists:trades,blockchain_trade_id',
            'newStatus' => 'required|in:fulfilled,cancelled',
            'buyerAddress' => 'nullable|string', // L'adresse de l'acheteur, si applicable
        ]);

        $trade = Trade::where('blockchain_trade_id', $validated['offerId'])->first();

        if ($trade) {
            $trade->status = $validated['newStatus'];
            if ($validated['newStatus'] === 'fulfilled' && isset($validated['buyerAddress'])) {
                $trade->buyer_wallet_address = $validated['buyerAddress'];
            }
            $trade->save();
            Log::info('Statut du trade mis à jour par le listener', ['id' => $validated['offerId'], 'status' => $validated['newStatus']]);
            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'not_found'], 404);
    }



}