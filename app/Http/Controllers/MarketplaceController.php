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

    /**
     * Achat direct de SNT contre AVAX on-chain (rechargement du token de jeu).
     *
     * Arbitrage validé (Q1) : exige un tx_hash unique ET vérifie le transfert
     * on-chain via le bridge (verifySntTransfer) AVANT de créditer. Un échec de
     * vérification ne crédite PAS et n'écrit PAS le ledger.
     *
     * Idempotence : le ledger est protégé par l'unique contrainte DB sur
     * tx_hash — une course concurrente (retry client, double-clic) lève
     * UniqueConstraintViolationException et la route répond 422 sans avoir
     * re-crédité (tout le bloc est dans une transaction).
     */
    public function handleTokenPurchase(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'tx_hash' => 'required|string|max:255|unique:token_purchases,tx_hash',
        ]);

        $buyer = $request->user();
        $amountPurchased = (float) $validated['amount'];
        $txHash = $validated['tx_hash'];

        // --- Vérification on-chain via le bridge (même pattern que ShopController) ---
        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $verifyResponse = \App\Helpers\Web3Helper::verifySntTransfer(
            $nodeUrl,
            $txHash,
            $amountPurchased,
            $buyer->wallet_address
        );

        if (!isset($verifyResponse['success']) || !$verifyResponse['success']) {
            Log::warning('Marketplace purchase: vérification SNT on-chain échouée', [
                'user_id' => $buyer->id,
                'tx_hash' => $txHash,
                'expected_amount' => $amountPurchased,
                'response' => $verifyResponse,
            ]);

            return response()->json([
                'message' => 'La vérification de la transaction on-chain a échoué. Aucun crédit effectué.',
                'errors' => ['tx_hash' => ['La transaction n\'a pas pu être vérifiée.']],
            ], 422);
        }

        // --- Crédit + écriture du ledger, atomiques et anti-course ---
        try {
            DB::transaction(function () use ($buyer, $amountPurchased, $txHash) {
                // Verrou ligne utilisateur : sérialise les achats concurrents du même wallet.
                User::where('id', $buyer->id)->lockForUpdate()->first();

                $buyer->increment('token_balance', $amountPurchased);

                \App\Models\TokenPurchase::create([
                    'user_id' => $buyer->id,
                    'amount' => $amountPurchased,
                    'quantity' => 1,
                    'source' => 'marketplace_purchase',
                    'tx_hash' => $txHash,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Un tx_hash identique a été validé en parallèle : on NE crédite PAS.
            Log::warning('Marketplace purchase: tx_hash déjà crédité (course concurrente)', [
                'user_id' => $buyer->id,
                'tx_hash' => $txHash,
            ]);

            return response()->json([
                'message' => 'Cette transaction a déjà été utilisée pour un achat.',
                'errors' => ['tx_hash' => ['Ce tx_hash a déjà été enregistré.']],
            ], 422);
        }

        // --- OPTIMISATION ---
        // Le seuil de solde a été retiré : la validation du parrainage se fait
        // désormais à la PREMIÈRE SESSION du filleul (cf. PoolReconstructionService).
        $pendingReferral = Referral::where('referred_id', $buyer->id)
                                ->where('status', 'pending')
                                ->exists();

        if ($pendingReferral) {
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

        Log::info('Relaying create-offer request asynchronously using CreateOfferJob', $validated);

        \App\Jobs\CreateOfferJob::dispatch([
            'sellerAddress' => $user->wallet_address,
            'sntAmount' => $validated['snt_amount'],
            'avaxAmount' => $validated['avax_amount'],
            'durationHours' => $validated['expiry_hours'],
        ]);

        return response()->json([
            'status' => 'pending',
            'message' => 'L\'offre est en cours de création.'
        ]);
    }


}