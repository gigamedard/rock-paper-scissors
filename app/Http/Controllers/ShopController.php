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

        // Vérification synchrone auprès du pont Node.js
        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $verifyResponse = \App\Helpers\Web3Helper::verifySntTransfer($nodeUrl, $txHash, $card->price, $user->wallet_address);

        if (isset($verifyResponse['error'])) {
             return response()->json(['error' => $verifyResponse['error']], 400);
        }
        if (!isset($verifyResponse['success']) || !$verifyResponse['success']) {
             return response()->json(['error' => 'Vérification de la transaction échouée.'], 400);
        }

        \Illuminate\Support\Facades\Log::info("Achat de carte On-Chain validé", ['user' => $user->id, 'card' => $card->id, 'tx_hash' => $txHash]);

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
            'tx_hash' => $txHash,
        ]);

        // Sync limits to blockchain
        $user->load('userCards.card');
        $user->syncLimitsToBlockchain();

        return response()->json([
            'message' => 'Carte achetée avec succès !',
            'user_card' => $userCard->load('card'),
            'new_balance' => $user->token_balance
        ]);
    }
}
