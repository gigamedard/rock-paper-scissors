<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReferralService;
use App\Models\User;
use App\Models\Referral; // <-- AJOUTE CETTE LIGNE
use App\Models\Trade; // <-- Assure-toi que le modèle Trade est importé
use Illuminate\Support\Facades\DB; // <-- Assure-toi que DB est importé
use Carbon\Carbon; // <-- AJOUTE CETTE LIGNE EN HAUT
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MarketplaceController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function handleTokenPurchase(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $buyer = $request->user();
        $amountPurchased = $validated['amount'];
        $minimumPurchaseForReferral = 5;

        $buyer->increment('token_balance', $amountPurchased);

        // --- OPTIMISATION ---
        // On vérifie d'abord si l'utilisateur a un parrainage en attente.
        $pendingReferral = Referral::where('referred_id', $buyer->id)
                                ->where('status', 'pending')
                                ->exists(); // exists() est plus performant que first() si on n'a besoin que de savoir si la ligne existe.

        // Si le solde est suffisant ET qu'il y a bien un parrainage à valider, alors on appelle le service.
        if ($pendingReferral && $buyer->fresh()->token_balance >= $minimumPurchaseForReferral) {
            $this->referralService->processReferralValidation($buyer);
        }

        return response()->json([
            'message' => 'Achat réussi !',
            'new_balance' => $buyer->fresh()->token_balance,
        ]);
    }


    public function getStats(Request $request)
    {
        // On calcule la date d'il y a 24 heures de manière fiable avec Carbon
        $date24hAgo = Carbon::now()->subDay();

        // On utilise les méthodes d'Eloquent pour construire la requête
        $stats = Trade::select(
            DB::raw('COUNT(*) as total_trades'),
            DB::raw('SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) as active_trades'),
            DB::raw('SUM(snt_amount) as total_snt_volume'),
            DB::raw('SUM(avax_amount) as total_avax_volume'),
            DB::raw('AVG(CASE WHEN snt_amount > 0 THEN avax_amount / snt_amount ELSE 0 END) as average_price')
        )
        // On compte les trades des dernières 24h en se basant sur la date calculée par Laravel/Carbon
        ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_24h_trades', [$date24hAgo])
        ->first();
        
        // Assurer que les valeurs ne sont pas nulles si la table est vide
        foreach ($stats->getAttributes() as $key => $value) {
            if ($value === null) {
                $stats->{$key} = 0;
            }
        }

        return response()->json($stats);
    }

    /**
     * Retourne la liste des trades actifs pour le frontend.
     */
    public function getActiveTrades(Request $request)
    {
        $user = $request->user();

        $trades = \App\Models\Trade::where('status', 'open')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        // La fonction 'transform' modifie chaque élément de la collection
        $trades->transform(function ($trade) use ($user) {
            $trade->price_per_snt = $trade->snt_amount > 0 ? $trade->avax_amount / $trade->snt_amount : 0;
            
            $trade->is_own_trade = ($user && strtolower($user->wallet_address) === strtolower($trade->seller_wallet_address));
            
            $trade->seller_address = substr($trade->seller_wallet_address, 0, 6) . '...' . substr($trade->seller_wallet_address, -4);
            
            // La ligne ci-dessous est celle qui contenait probablement la faute de frappe.
            // C'est maintenant corrigé.
            $trade->blockchain_id = $trade->blockchain_trade_id;

            return $trade;
        });

        return response()->json(['trades' => $trades]);
    }

    public function createOffer(Request $request)
    {
        $validated = $request->validate([
            'snt_amount' => 'required|numeric|min:1',
            'avax_amount' => 'required|numeric|min:0.01',
            'expiry_hours' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        Log::info('Relaying create-offer request to Node.js server', $validated);

        // On transmet la requête au serveur Node.js qui gère les transactions blockchain
        $response = Http::post('http://127.0.0.1:3000/create-offer', [
            'sellerAddress' => $user->wallet_address,
            'sntAmount' => $validated['snt_amount'],
            'avaxAmount' => $validated['avax_amount'],
            'durationHours' => $validated['expiry_hours'],
        ]);

        if (!$response->successful()) {
            Log::error('Node.js server failed to create offer', ['response' => $response->body()]);
            return response()->json(['error' => 'La création de l"offre a échoué.'], 500);
        }

        return $response->json();
    }

}