<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Models\User;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator; // <-- AJOUTE CETTE LIGNE

class InternalTradeController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function create(Request $request)
    {
        // CORRECTION N°1 : On log le corps brut en le concaténant avec un POINT.
        Log::info('==> [LISTENER] CORPS BRUT REÇU sur /create: ' . $request->getContent());

        // CORRECTION N°2 : On parse le JSON manuellement
        $data = json_decode($request->getContent(), true);

        // CORRECTION N°3 : On valide le tableau $data
        $validator = Validator::make($data, [
            'offerId' => 'required|numeric|unique:trades,blockchain_trade_id',
            'seller' => 'required|string',
            'sntAmount' => 'required|numeric',
            'avaxAmount' => 'required|numeric',
            'expiresAt' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Log::error('==> [LISTENER] Echec validation /create', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        Trade::create([
            'blockchain_trade_id' => $validatedData['offerId'],
            'seller_wallet_address' => $validatedData['seller'],
            'snt_amount' => $validatedData['sntAmount'],
            'avax_amount' => $validatedData['avaxAmount'],
            'status' => 'open',
            'expires_at' => Carbon::createFromTimestamp($validatedData['expiresAt']),
        ]);
        Log::info('==> [LISTENER] Trade créé avec succès dans la BDD.', ['id' => $validatedData['offerId']]);

        return response()->json(['status' => 'success'], 201);
    }

    public function updateStatus(Request $request)
        {
            Log::info('==> [LISTENER] CORPS BRUT REÇU sur /update-status: ' . $request->getContent());
            $data = json_decode($request->getContent(), true);

            if (empty($data) || !isset($data['offerId']) || !isset($data['newStatus'])) {
                Log::error('==> [LISTENER] Echec /update-status: Corps JSON vide ou invalide.', $data ?? []);
                return response()->json(['status' => 'invalid_json'], 400);
            }

            try {
                // On commence une transaction de base de données
                DB::transaction(function () use ($data) {
                    
                    // 1. On trouve le trade
                    $trade = Trade::where('blockchain_trade_id', $data['offerId'])->firstOrFail();

                    // 2. On met à jour le trade
                    $trade->status = $data['newStatus'];

                    if (isset($data['buyerAddress'])) {
                        $trade->buyer_wallet_address = $data['buyerAddress'];
                        $trade->save(); // Save buyer address
                        
                        // NOTE: Balance update is now handled by the SNT Transfer Listener (app.js)
                        // This prevents double-counting tokens if we listen to both Marketplace and Token events.
                        // We still log the event for debugging.
                        Log::info('==> [LISTENER] Trade fulfilled. Buyer: ' . $data['buyerAddress'] . '. Balance update delegated to Transfer event.');
                    }
                    
                    $trade->save();
                });

                Log::info('==> [LISTENER] SUCCÈS : Transaction BDD terminée pour /update-status.', ['id' => $data['offerId']]);
                return response()->json(['status' => 'success']);

            } catch (\Exception $e) {
                // Si quelque chose échoue (le trade n'est pas trouvé, etc.), on log l'erreur
                Log::error('==> [LISTENER] ECHEC CRITIQUE de la transaction /update-status: ' . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        public function triggerReferralCheck(Request $request)
        {
            Log::info('==> [LISTENER] CORPS BRUT REÇU sur /trigger-referral-check: ' . $request->getContent());
            $data = json_decode($request->getContent(), true);

            if (empty($data) || !isset($data['buyer_address'])) {
                Log::error('==> [LISTENER] Echec /trigger-referral: Corps JSON vide ou invalide.', $data ?? []);
                return response()->json(['status' => 'invalid_json'], 400);
            }

            $buyerAddress = strtolower($data['buyer_address']);
            $referredUser = User::where('wallet_address', $buyerAddress)->first();

            if (!$referredUser) {
                Log::warning('==> [LISTENER] Echec /trigger-referral: Utilisateur non trouvé.', ['address' => $buyerAddress]);
                return response()->json(['status' => 'user_not_found'], 404);
            }

            $minimumBalanceForReferral = 5;
            
            // On rafraîchit le modèle pour être sûr d'avoir le solde mis à jour par l'étape précédente
            $referredUser->refresh(); 
            $pendingReferral = Referral::where('referred_id', $referredUser->id)->where('status', 'pending')->exists();
            
            Log::info('==> [LISTENER] Vérification des conditions de parrainage.', [
                'user_id' => $referredUser->id,
                'has_pending_referral' => $pendingReferral,
                'token_balance' => $referredUser->token_balance,
                'balance_is_sufficient' => $referredUser->token_balance >= $minimumBalanceForReferral
            ]);

            if ($pendingReferral && $referredUser->token_balance >= $minimumBalanceForReferral) {
                Log::info('==> [LISTENER] Conditions remplies, déclenchement de la validation.');
                $this->referralService->processReferralValidation($referredUser);
                return response()->json(['status' => 'referral_processed']);
            }

            Log::info('==> [LISTENER] Conditions non remplies pour la validation.');
            return response()->json(['status' => 'conditions_not_met']);
        }

    public function syncTransfer(Request $request)
    {
        // 1. Log & Decode
        Log::info('==> [LISTENER] Sync Transfer Event: ' . $request->getContent());
        $data = json_decode($request->getContent(), true);

        if (empty($data) || !isset($data['to'])) {
            return response()->json(['status' => 'invalid_json'], 400);
        }

        $fromAddress = $data['from'] ?? null;
        $toAddress = $data['to'];
        $amount = $data['amount'] ?? 0; // Amount in human readable units (e.g. 5.0)

        DB::transaction(function () use ($fromAddress, $toAddress, $amount) {
            // 2. Handle Sender (Decrement)
            if ($fromAddress && $fromAddress !== '0x0000000000000000000000000000000000000000') {
                $sender = User::where('wallet_address', strtolower($fromAddress))->first();
                if ($sender) {
                    $sender->decrement('token_balance', $amount);
                    Log::info("Décrémenté $amount tokens de $fromAddress");
                }
            }

            // 3. Handle Receiver (Increment)
            $receiver = User::where('wallet_address', strtolower($toAddress))->first();
            if ($receiver) {
                $receiver->increment('token_balance', $amount);
                Log::info("Incrémenté $amount tokens pour $toAddress");

                // 4. Trigger Referral Check DIRECTLY within the transaction
                $this->checkReferralForUser($receiver);
            }
        });

        return response()->json(['status' => 'synced']);
    }

    private function checkReferralForUser(User $user)
    {
        $minimumBalance = 5;
        $pendingReferral = Referral::where('referred_id', $user->id)->where('status', 'pending')->exists();

        // Refresh user to get updated balance is optional inside transaction if we just incremented, 
        // but $user->increment changes DB, not the model instance immediately unless refreshed or set.
        // Usually increment() doesn't update the model instance attributes in memory automatically in old Laravel, 
        // but we can just use fresh() or refresh().
        $currentBalance = $user->fresh()->token_balance;

        if ($pendingReferral && $currentBalance >= $minimumBalance) {
            Log::info("Validation parrainage déclenchée pour User {$user->id} suite à un transfert.");
            $this->referralService->processReferralValidation($user);
        }
    }
    }
