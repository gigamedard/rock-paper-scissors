<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index()
    {
        // Lister uniquement les cartes actives
        $cards = \App\Models\Card::where('is_active', true)->get();
        return response()->json($cards);
    }

    public function inventory(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userCards = \App\Models\UserCard::with('card')
            ->where('user_id', $user->id)
            ->get();

        return response()->json($userCards);
    }

    public function buy(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'card_id' => 'required|exists:cards,id',
            'tx_hash' => 'required|string|unique:user_cards,tx_hash',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $card = \App\Models\Card::where('id', $validated['card_id'])->where('is_active', true)->firstOrFail();

        $txHash = $validated['tx_hash'];
        $quantity = $validated['quantity'] ?? 1;

        $createdCards = [];
        for ($i = 0; $i < $quantity; $i++) {
            $createdCards[] = \App\Models\UserCard::create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'status' => 'pending',
                'remaining_sessions' => $card->duration_type === 'sessions' ? $card->duration_value : null,
                'expires_at' => null, // Calculated upon verification for accuracy
                'tx_hash' => $txHash,
            ]);
        }

        // Lancer la vérification et la synchronisation de façon asynchrone (une seule fois pour le lot)
        \App\Jobs\VerifyCardPurchaseJob::dispatch($createdCards[0]);

        // Rafraîchir les cartes pour avoir le statut final si le queue driver est 'sync' (comme en test)
        foreach ($createdCards as $uCard) {
            $uCard->refresh();
            $uCard->load('card');
        }

        return response()->json([
            'message' => 'L\'achat a été initié et est en cours de traitement.',
            'user_cards' => $createdCards,
            'user_card' => $createdCards[0],
            'new_balance' => $user->token_balance
        ]);

    }
}
