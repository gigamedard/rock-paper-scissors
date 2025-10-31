<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\UserBalanceTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\testevent;

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
        $balance = $validated['balance'];

        try {
            $user = User::where('wallet_address', $walletAddress)->first();

            if ($user) {
                $user->update(['balance' => $balance]); // From the trait
            } else {
                $user = $this->createNewUser($walletAddress, $balance); // From the trait
            }

            Log::info("User balance updated: Address: {$walletAddress}, Balance: {$balance}");

            try {
              event(new testevent(1,$balance));
            } catch (\Throwable $e) {
                Log::error("Error emit event: {$e->getMessage()}");
            }
            


            return response()->json([
                'message' => 'User balance updated successfully.',
                'address' => $walletAddress,
                'balance' => $balance,
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
        $nodeWorkerUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
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


}
