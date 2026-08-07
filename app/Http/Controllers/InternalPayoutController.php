<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\Web3Helper;

class InternalPayoutController extends Controller
{
    /**
     * Trigger a payout from the contract to a user wallet.
     * Expected JSON payload:
     *   {
     *     "wallet_address": "0x...",
     *     "amount": "0.001"   // amount in ETH (string or numeric)
     *   }
     */
    public function payout(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $walletAddress = strtolower($validated['wallet_address']);
        $amountEth = $validated['amount'];

        Log::info("Internal payout request queued: {$walletAddress}, {$amountEth} ETH");

        try {
            \App\Jobs\ProcessPayoutJob::dispatch($walletAddress, $amountEth);

            return response()->json([
                'success' => true,
                'message' => 'Payout queued successfully.',
                'wallet_address' => $walletAddress,
                'amount_eth' => $amountEth,
            ], 202);
        } catch (\Throwable $e) {
            Log::error('Error queuing internal payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error queuing payout.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
