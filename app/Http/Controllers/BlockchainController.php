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

            if ($user) {
                // Check if balance dropped to 0 (Bankruptcy risk)
                $oldBalance = $user->balance;
                Log::info("Updating existing user balance", ['old' => $oldBalance, 'new' => $balanceEth]);
                
                $updateData = ['balance' => $balanceEth];
                if ($user->autoplay_active && in_array($user->status, ['awaiting_onchain', 'stopped']) && $balanceEth >= $user->bet_amount) {
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
            } else {
                Log::info("Creating new user", ['wallet' => $walletAddress, 'balance' => $balanceEth]);
                $user = $this->createNewUser($walletAddress, $balanceEth); // From the trait
                Log::info("New user created", ['id' => $user->id]);
            }

            Log::info("User balance updated: Address: {$walletAddress}, Balance: {$balanceEth} ETH (from {$balanceWei} wei)");

            try {
              event(new BalanceUpdated($user->id, $balanceEth));
            } catch (\Throwable $e) {
                Log::error("Error emit event: {$e->getMessage()}");
            }

            // ADDED: Referral Validation Check (Mimicking/Improving)
            // Even if this updates ETH balance, we check SNT token balance.
             try {
                $minimumBalance = 5;
                if ($user->fresh()->token_balance >= $minimumBalance) {
                     // We need to instantiate the service manually or inject it. 
                     // Since we didn't inject it in the controller constructor yet, let's use app() helper or modify constructor.
                     // Modifying constructor is cleaner but riskier if dependencies vary.
                     // Let's use resolving from container for minimal disruption in this method.
                     $referralService = app(\App\Services\ReferralService::class);
                     $referralService->processReferralValidation($user);
                }
            } catch (\Throwable $e) {
                Log::error("Error checking referral in updateUserBalance: {$e->getMessage()}");
            }
            


            return response()->json([
                'message' => 'User balance updated successfully.',
                'address' => $walletAddress,
                'balance_eth' => $balanceEth,
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
                    $user->autoplay_active = false; // Empêcher le recyclage automatique après claim
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
