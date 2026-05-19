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
            'card_id' => 'required|exists:cards,id'
        ]);

        $card = \App\Models\Card::where('id', $validated['card_id'])->where('is_active', true)->firstOrFail();

        // Note: La vérification de la transaction Web3 et la déduction des tokens SNT 
        // sont gérées de manière asynchrone par le pont Node.js (via syncTransfer).
        // Le frontend envoie le tx_hash pour l'audit.
        $txHash = $request->input('tx_hash');
        if ($txHash) {
            \Illuminate\Support\Facades\Log::info("Achat de carte On-Chain", ['user' => $user->id, 'card' => $card->id, 'tx_hash' => $txHash]);
        }

        // Ajouter la carte à l'inventaire
        $expiresAt = null;
        if ($card->duration_type === 'time') {
            $expiresAt = now()->addHours($card->duration_value);
        }

        $userCard = \App\Models\UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'status' => 'available',
            'remaining_sessions' => $card->duration_type === 'sessions' ? $card->duration_value : null,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'message' => 'Carte achetée avec succès !',
            'user_card' => $userCard->load('card'),
            'new_balance' => $user->token_balance
        ]);
    }
}
