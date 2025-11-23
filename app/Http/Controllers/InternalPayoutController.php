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

        Log::info("Internal payout request: {$walletAddress}, {$amountEth} ETH");

        try {
            $nodeWorkerUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
            $result = Web3Helper::sendPayement($nodeWorkerUrl, $walletAddress, $amountEth);

            if (isset($result['success']) && $result['success']) {
                Log::info("Internal payout successful: TxHash {$result['txHash']}");
                return response()->json([
                    'success' => true,
                    'message' => 'Payout sent successfully.',
                    'txHash' => $result['txHash'],
                    'wallet_address' => $walletAddress,
                    'amount_eth' => $amountEth,
                ], 200);
            }

            Log::error('Internal payout failed: ' . json_encode($result));
            return response()->json([
                'success' => false,
                'message' => 'Payout failed.',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Throwable $e) {
            Log::error('Error in internal payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing payout.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
