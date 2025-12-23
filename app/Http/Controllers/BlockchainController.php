<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\UserBalanceTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\testevent;
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

        try {
            $user = User::where('wallet_address', $walletAddress)->first();

            if ($user) {
                $user->update(['balance' => $balanceEth]); // From the trait
            } else {
                $user = $this->createNewUser($walletAddress, $balanceEth); // From the trait
            }

            Log::info("User balance updated: Address: {$walletAddress}, Balance: {$balanceEth} ETH (from {$balanceWei} wei)");

            try {
              event(new testevent(1,$balanceEth));
            } catch (\Throwable $e) {
                Log::error("Error emit event: {$e->getMessage()}");
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

    public function getArtefacts()
    {
        Log::info("Fetching game config from Node.js worker...");
        // Récupère l'URL du worker et le secret depuis ton .env
        $nodeWorkerUrl = config('app.node_worker_url');
        $internalSecret = config('app.INTERNAL_API_SECRET');

        Log::info("Internal API Secret: {$internalSecret}");
        Log::info("Node Worker URL: {$nodeWorkerUrl}");


        try {
            // Appelle le serveur Node.js en passant le header secret
            $response = Http::withHeaders([
                'X-Internal-Secret' => $internalSecret,
                'Accept' => 'application/json',
            ])->get("{$nodeWorkerUrl}/get-game-config");
            Log::info("Response body: " . $response->body());
            // Si l'appel échoue
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Erreur: --Le service de configuration est indisponible.--!'
                ], 503); // 503 Service Unavailable
            }

            // Si l'appel réussit, renvoie directement la réponse JSON de Node.js
            return $response->json();

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
            // Get Node.js worker URL from config
            $nodeWorkerUrl = config('app.node_worker_url');

            // Call Node.js worker to send payment via smart contract
            $result = Web3Helper::sendPayement($nodeWorkerUrl, $walletAddress, $amountEth);

            if (isset($result['success']) && $result['success']) {
                Log::info("Payout successful: TxHash: {$result['txHash']}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payout sent successfully.',
                    'txHash' => $result['txHash'],
                    'wallet_address' => $walletAddress,
                    'amount_eth' => $amountEth,
                ], 200);
            } else {
                Log::error("Payout failed: " . json_encode($result));
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payout failed.',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

        } catch (\Throwable $e) {
            Log::error("Error triggering payout: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Error triggering payout.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
