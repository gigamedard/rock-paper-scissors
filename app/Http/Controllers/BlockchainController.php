<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\UserBalanceTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\BalanceUpdated;
use App\Helpers\Web3Helper;
use Illuminate\Support\Facades\Http;
class BlockchainController extends Controller
{
    use UserBalanceTrait; // Include the trait

    public function updateUserBalance(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
            'balance' => 'required|numeric|min:0',
        ]);

        $walletAddress = strtolower($validated['wallet_address']);
        $balanceWei = $validated['balance'];
        
        // Convert from wei to ETH for database storage
        $balanceEth = Web3Helper::weiToEther($balanceWei);

        Log::info("updateUserBalance called", ['wallet' => $walletAddress, 'wei' => $balanceWei]);
        try {
            $user = User::where('wallet_address', $walletAddress)->first();
            Log::info("User search result", ['found' => $user ? true : false, 'id' => $user->id ?? null]);

            $p3Reinit = false;

            if ($user) {
                // Check if balance dropped to 0 (Bankruptcy risk)
                $oldBalance = $user->balance;
                Log::info("Updating existing user balance", ['old' => $oldBalance, 'new' => $balanceEth]);

                $bankrollEnabled = (bool) config('bankroll.enabled', false);
                $p3Active = $bankrollEnabled && $user->bankroll_total !== null;
                $inPool = ($user->status === 'in_pool')
                    || ((float) $user->battle_balance > 0)
                    || ($user->pool_id !== null);
                $hasClaimOrCooldown = $user->payout_signature !== null
                    || ($user->cooldown_until && $user->cooldown_until->isFuture());

                // P3 — dépôt pendant un pool / claim / cooldown : le total on-chain
                // est cumulatif. On le mémorise sans toucher à la balance de jeu ;
                // il sera appliqué en fin de pool (recalcul C/R/B avec la cible choisie).
                if ($p3Active && ($inPool || $hasClaimOrCooldown)) {
                    $user->pending_bankroll_total = $balanceEth;
                    $user->save();
                    Log::info("P3 top-up deferred (in pool)", [
                        'user' => $user->id,
                        'onchain_total' => $balanceEth,
                        'pool_id' => $user->pool_id,
                    ]);

                    return response()->json([
                        'message' => 'Deposit recorded; P3 bankroll reinitialization deferred until the pool ends.',
                        'address' => $walletAddress,
                        'balance_eth' => (float) $user->balance,
                        'balance_wei' => $balanceWei,
                        'pending_bankroll_total' => $balanceEth,
                    ], 200);
                }

                // P3 — dépôt hors pool sur un bankroll actif : recalcul C/R/B
                // à partir du nouveau total on-chain et de la cible du joueur.
                if ($p3Active) {
                    try {
                        $bs = app(\App\Services\BankrollSessionService::class);
                        $target = (float) ($user->target_q ?? 0);
                        $bs->reinitializeFromTotal($user, (float) $balanceEth, $target > 1.0 ? $target : null);
                        $p3Reinit = true;
                        Log::info("P3 bankroll reinitialized from on-chain total", [
                            'user' => $user->id,
                            'total' => $balanceEth,
                            'C' => $user->session_capital,
                            'R' => $user->bankroll_recovery_reserve,
                            'X' => $user->recovery_factor,
                            'T' => $user->bankroll_target,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('P3 reinitialize failed for user ' . $user->id . ': ' . $e->getMessage());
                    }
                }

                if (! $p3Active) {
                    $updateData = ['balance' => $balanceEth];
                    // GUARD (fix perte de gains) : ne PAS ré-enrôler en autoplay un joueur
                    // en statut 'stopped' qui a un claim en attente (payout_signature) ou un
                    // cooldown actif (session target atteint). Sinon l'indexeur bridge
                    // (UserBalanceUpdated émis par /generate-signature) le repasse
                    // 'available' avant qu'il n'ait retiré ses gains → gains irrécupérables.
                    $cooldownActive = $user->cooldown_until && $user->cooldown_until->isFuture();
                    // Réactivation après un crédit/dépôt : bots (autoplay) ET humains.
                    // (guards : pas de claim en attente, pas de cooldown actif).
                    if (in_array($user->status, ['awaiting_onchain', 'stopped'])
                        && $balanceEth >= $user->bet_amount
                        && $user->payout_signature === null
                        && !$cooldownActive
                    ) {
                        $updateData['status'] = 'available';
                        Log::info("User {$user->id} status updated to available because of positive balance and active autoplay");
                    }

                    $updated = $user->update($updateData);
                    Log::info("Update result", ['success' => $updated]);

                    if ($oldBalance > 0 && $balanceEth <= 0.0001) { // Near zero
                        \App\Models\GameNotification::create([
                            'user_id' => $user->id,
                            'type' => 'BANKRUPTCY',
                            'data' => ['balance' => $balanceEth]
                        ]);
                    }
                }
            } else {
                Log::info("Creating new user", ['wallet' => $walletAddress, 'balance' => $balanceEth]);
                $user = $this->createNewUser($walletAddress, $balanceEth); // From the trait
                Log::info("New user created", ['id' => $user->id]);
            }

            $effectiveBalance = $p3Reinit ? (float) $user->balance : (float) $balanceEth;
            Log::info("User balance updated: Address: {$walletAddress}, Balance: {$effectiveBalance} (onchain {$balanceEth}) (from {$balanceWei} wei)");

            try {
              event(new BalanceUpdated($user->id, $effectiveBalance));
            } catch (\Throwable $e) {
                Log::error("Error emit event: {$e->getMessage()}");
            }

            // Referral Validation Check — le seuil de solde a été retiré :
            // la validation se fait désormais à la PREMIÈRE SESSION du filleul.
             try {
                 $referralService = app(\App\Services\ReferralService::class);
                 $referralService->processReferralValidation($user);
            } catch (\Throwable $e) {
                Log::error("Error checking referral in updateUserBalance: {$e->getMessage()}");
            }
            


            return response()->json([
                'message' => 'User balance updated successfully.',
                'address' => $walletAddress,
                'balance_eth' => $effectiveBalance,
                'onchain_balance_eth' => (float) $balanceEth,
                'balance_wei' => $balanceWei,
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error updating user balance: {$e->getMessage()}");

            return response()->json([
                'message' => 'Error updating user balance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pré-synchro on-chain + régénération de la signature avant un claim.
     * Appelé par le front lorsqu'il reçoit « Amount exceeds user balance »
     * (la tx updateUserBalance du bridge n'était pas encore minée).
     */
    public function syncClaim(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        try {
            app(\App\Services\SessionManager::class)->preSyncHumanClaim($user);
            $user->refresh();

            return response()->json([
                'success' => true,
                'balance' => (float) $user->balance,
                'payout_signature' => $user->payout_signature,
                'payout_deadline' => $user->payout_deadline,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CLAIM_SYNC] failed for user ' . $user->id . ': ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleClaim(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
        ]);

        $walletAddress = strtolower($validated['wallet_address']);

        Log::info("handleClaim called", ['wallet' => $walletAddress]);

        try {
            $user = User::where('wallet_address', $walletAddress)->first();

            if ($user) {
                $lock = \Illuminate\Support\Facades\Cache::lock('claim_pool_' . $user->id, 5); // 5 seconds lock
                
                if (!$lock->get()) {
                    return response()->json(['error' => 'Action already in progress'], 429);
                }

                try {
                    $user->balance = 0;
                    $user->payout_signature = null;
                    $user->status = 'stopped';
                    $user->save();

                    Log::info("✅ [Internal API] User {$user->wallet_address} state fully reset after claim.");
                    return response()->json(['success' => true, 'message' => 'User state reset.']);
                } finally {
                    $lock->release();
                }
            }    

            return response()->json([
                'message' => 'User not found.',
            ], 404);

        } catch (\Throwable $e) {
            Log::error("Error handling claim: {$e->getMessage()}");

            return response()->json([
                'message' => 'Error handling claim.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getArtefacts()
    {
        Log::info("Fetching game config from Node.js worker...");
        // Récupère l'URL du worker et le secret depuis ton .env
        $nodeWorkerUrl = config('app.NODE_WORKER_URL');
        $internalSecret = config('app.INTERNAL_API_SECRET');

        try {
            // Cache the Node.js config for 5 minutes (300 seconds)
            $nodeConfig = \Illuminate\Support\Facades\Cache::remember('game_config', 300, function () use ($nodeWorkerUrl, $internalSecret) {
                Log::info("Making network request to fetch game config from Node.js...");
                $response = Http::withHeaders([
                    'X-Internal-Secret' => $internalSecret,
                    'Accept' => 'application/json',
                ])->get("{$nodeWorkerUrl}/get-game-config");

                if (!$response->successful()) {
                    throw new \Exception("Configuration service unavailable.");
                }

                return $response->json();
            });

            // On enrichit avec les paramètres "Business" stockés en base de données Laravel
            $dbConfig = [
                'security_coefficient' => \App\Models\GameSetting::getValue('security_coefficient', 1000), // Default 1000 if not set
                'game_fee_percentage' => \App\Models\GameSetting::getValue('game_fee_percentage', 5.0),
                'smart_contract_fee_percentage' => \App\Models\GameSetting::getValue('smart_contract_fee_percentage', 2.5), // Default 2.5% Smart Contract Fee
                'min_bet_eth' => \App\Models\GameSetting::getValue('min_bet_eth', 0.01),
                // P3 : modèle bankroll (dépôt unique B = C + R).
                'bankroll_enabled' => (bool) config('bankroll.enabled', false),
                'bankroll_session_target_q' => (float) config('bankroll.session_target_q', 1.05),
                'bankroll_fee_rate' => (float) config('bankroll.fee_rate', 0.005),
                'bankroll_security_coefficient' => (int) config('bankroll.security_coefficient', 1000),
                'bankroll_recovery_factor' => \App\Services\BankrollSessionService::recoveryFactor(
                    (float) config('bankroll.session_target_q', 1.05),
                    (float) config('bankroll.fee_rate', 0.005)
                ),
                'bankroll_refund_pct' => (float) config('refund.refund_pct', 0.25),
            ];

            // Fusionner les tableaux (les params DB écrasent ceux du Node si conflit, sauf si on inverse)
            return array_merge($nodeConfig, $dbConfig);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur de communication avec le service interne.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function triggerPayout(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $walletAddress = strtolower($validated['wallet_address']);
        $amountEth = $validated['amount'];

        Log::info("Triggering payout: Wallet: {$walletAddress}, Amount: {$amountEth} ETH");

        try {
            \App\Jobs\ProcessPayoutJob::dispatch($walletAddress, (float) $amountEth);

            return response()->json([
                'success' => true,
                'status' => 'pending',
                'message' => 'Payout transaction has been queued.'
            ], 202);

        } catch (\Throwable $e) {
            Log::error("Error triggering payout: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Error triggering payout.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function updateSetting(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'required',
            'type' => 'nullable|string',
            'group' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $key = $validated['key'];
        $value = $validated['value'];
        $type = $validated['type'] ?? 'string';
        $group = $validated['group'] ?? 'blockchain';
        $description = $validated['description'] ?? "Auto-updated from Smart Contract event";

        try {
            \App\Models\GameSetting::setValue($key, $value, $type, $group, $description);
            
            Log::info("Game setting updated via internal API: {$key} = {$value}");

            return response()->json([
                'success' => true,
                'message' => "Setting {$key} updated successfully.",
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Error updating game setting: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Error updating setting.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
