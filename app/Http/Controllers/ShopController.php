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
            'tx_hash' => 'required|string|unique:user_cards,tx_hash'
        ]);

        $card = \App\Models\Card::where('id', $validated['card_id'])->where('is_active', true)->firstOrFail();

        $txHash = $validated['tx_hash'];

        // Ajouter la carte à l'inventaire avec un statut 'pending'
        $expiresAt = null;
        if ($card->duration_type === 'time') {
            $expiresAt = now()->addHours($card->duration_value);
        }

        $userCard = \App\Models\UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'status' => 'pending',
            'remaining_sessions' => $card->duration_type === 'sessions' ? $card->duration_value : null,
            'expires_at' => $expiresAt,
            'tx_hash' => $txHash,
        ]);

        // Lancer la vérification et la synchronisation de façon asynchrone
        \App\Jobs\VerifyCardPurchaseJob::dispatch($userCard);

        // Rafraîchir pour avoir le statut final si le queue driver est 'sync' (comme en test)
        $userCard->refresh();

        return response()->json([
            'message' => 'L\'achat de la carte a été initié et est en cours de traitement.',
            'user_card' => $userCard->load('card'),
            'new_balance' => $user->token_balance
        ]);

    }
}
