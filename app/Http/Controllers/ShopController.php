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
            $cardTxHash = ($i === 0) ? $txHash : $txHash . '_qty_' . $i;
            $createdCards[] = \App\Models\UserCard::create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'status' => 'pending',
                'remaining_sessions' => $card->duration_type === 'sessions' ? $card->duration_value : null,
                'expires_at' => null, // Calculated upon verification for accuracy
                'tx_hash' => $cardTxHash,
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

    /**
     * Active manuellement une carte achetée (statut 'pending' ou 'available').
     * Si l'utilisateur est en cooldown, la réduction est appliquée immédiatement
     * (cooldown rétroactif) pour un effet visible tout de suite.
     *
     * Option C : les cartes restent dormant jusqu'à activation manuelle par le joueur.
     */
    public function activateCard(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'user_card_id' => 'required|integer|exists:user_cards,id',
        ]);

        $userCard = \App\Models\UserCard::with('card')
            ->where('id', $validated['user_card_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$userCard) {
            return response()->json(['error' => 'Carte introuvable.'], 404);
        }

        // On accepte l'activation depuis 'pending' (vérifiée mais dormant) ou 'available'.
        if (!in_array($userCard->status, ['pending', 'available'])) {
            return response()->json(['error' => 'Cette carte ne peut plus être activée (statut: ' . $userCard->status . ').'], 422);
        }

        $card = $userCard->card;
        if (!$card) {
            return response()->json(['error' => 'Définition de carte introuvable.'], 500);
        }

        // Vérifier qu'il reste des sessions pour les cartes à durée "sessions"
        if ($card->duration_type === 'sessions' && $userCard->remaining_sessions !== null && $userCard->remaining_sessions <= 0) {
            return response()->json(['error' => 'Cette carte est épuisée (0 session restante).'], 422);
        }

        // 1. Marquer la carte comme 'available'
        $userCard->status = 'available';
        if ($card->duration_type === 'time' && !$userCard->expires_at) {
            $userCard->expires_at = now()->addHours($card->duration_value);
        }
        $userCard->save();

        // 2. Appliquer l'effet rétroactif si l'utilisateur est en cooldown
        $reducedMinutes = 0;
        $newCooldownUntil = null;

        if ($user->cooldown_until && \Carbon\Carbon::parse($user->cooldown_until)->isFuture()) {
            if ($card->effect_type === 'cooldown_reduction') {
                $effectValue = (float) $card->effect_value;
                $currentMinutes = now()->diffInMinutes(\Carbon\Carbon::parse($user->cooldown_until), false);
                $absEffect = abs($effectValue);

                if ($effectValue < 0) {
                    // Valeur négative = minutes fixes à soustraire (ex: -1000 = -1000 min)
                    $reducedMinutes = (int) min($absEffect, $currentMinutes);
                } elseif ($effectValue < 1) {
                    // Pourcentage (ex: 0.5 = -50%)
                    $reducedMinutes = (int) ($currentMinutes * $effectValue);
                } else {
                    // Minutes fixes positives (ex: 60 = -60 min)
                    $reducedMinutes = (int) min($effectValue, $currentMinutes);
                }
                $newCooldown = \Carbon\Carbon::parse($user->cooldown_until)->subMinutes($reducedMinutes);
                if ($newCooldown->isPast()) {
                    $newCooldown = null;
                }
                $user->cooldown_until = $newCooldown;
                $user->save();
                $newCooldownUntil = $newCooldown ? $newCooldown->toIso8601String() : null;

                // Sync le nouveau cooldown sur la blockchain
                try {
                    $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
                    app(\App\Helpers\Web3Helper::class)->setUserNextSessionTime(
                        $nodeUrl,
                        $user->wallet_address,
                        $newCooldown ? $newCooldown->timestamp : 0
                    );
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Activation carte: échec sync cooldown on-chain", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // 3. Consommer la carte IMMÉDIATEMENT après activation
        //    (décrémenter remaining_sessions pour les cartes à sessions, marquer consumed si 0)
        if ($card->duration_type === 'sessions') {
            if ($userCard->remaining_sessions > 0) {
                $userCard->remaining_sessions -= 1;
                if ($userCard->remaining_sessions <= 0) {
                    $userCard->status = 'consumed';
                }
                $userCard->save();
            }
        } elseif ($card->duration_type === 'time') {
            // Les cartes temporelles restent 'available' jusqu'à expiration
            // (pas de décrément, juste vérification de expires_at)
        }

        // 4. Sync les limites on-chain (la carte modifie min_cooldown)
        \App\Jobs\SyncUserLimitsJob::dispatch($user)->onQueue('limits');

        $remainingSessions = $userCard->remaining_sessions;

        return response()->json([
            'success' => true,
            'message' => $reducedMinutes > 0
                ? "Carte activée ! Cooldown réduit de {$reducedMinutes} minutes."
                : 'Carte activée. L\'effet s\'appliquera au prochain cooldown.',
            'user_card_id' => $userCard->id,
            'card_name' => $card->name,
            'effect_type' => $card->effect_type,
            'reduced_minutes' => $reducedMinutes,
            'cooldown_until' => $newCooldownUntil,
            'remaining_sessions' => $remainingSessions,
            'card_status' => $userCard->status,
        ]);
    }
}
