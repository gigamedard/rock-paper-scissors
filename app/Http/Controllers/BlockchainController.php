<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\UserBalanceTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                $this->updateUserBalanceInDb($user, $balance); // From the trait
            } else {
                $user = $this->createNewUser($walletAddress, $balance); // From the trait
            }

            Log::info("User balance updated: Address: {$walletAddress}, Balance: {$balance}");

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
}
