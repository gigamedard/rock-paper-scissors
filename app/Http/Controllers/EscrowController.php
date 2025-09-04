<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EscrowController extends Controller
{
    /**
     * Get all active trades from the smart contract.
     */
    public function getTrades(Request $request)
    {
        $start = $request->get('start', 0);
        $limit = $request->get('limit', 20);

        // Try to get from cache first
        $cacheKey = "escrow_trades_{$start}_{$limit}";
        $trades = Cache::get($cacheKey);

        if (!$trades) {
            try {
                // Call Node.js bridge to get trades from smart contract
                $response = Http::post(env('NODE_URL') . '/escrow/get-trades', [
                    'start' => $start,
                    'limit' => $limit
                ]);

                if ($response->successful()) {
                    $trades = $response->json();
                    // Cache for 30 seconds
                    Cache::put($cacheKey, $trades, 30);
                } else {
                    return response()->json(['error' => 'Failed to fetch trades'], 500);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching trades: ' . $e->getMessage());
                return response()->json(['error' => 'Service unavailable'], 503);
            }
        }

        return response()->json($trades);
    }

    /**
     * Create a new trade offer.
     */
    public function createTrade(Request $request)
    {
        $validated = $request->validate([
            'snt_amount' => 'required|numeric|min:0.00000001',
            'avax_amount' => 'required|numeric|min:0.00000001',
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/'
        ]);

        try {
            // Call Node.js bridge to create trade on smart contract
            $response = Http::post(env('NODE_URL') . '/escrow/create-trade', [
                'sntAmount' => $validated['snt_amount'],
                'avaxAmount' => $validated['avax_amount'],
                'walletAddress' => $validated['wallet_address']
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Clear trades cache
                $this->clearTradesCache();

                return response()->json([
                    'message' => 'Trade created successfully',
                    'transaction_hash' => $result['transactionHash'] ?? null,
                    'trade_id' => $result['tradeId'] ?? null
                ]);
            } else {
                $error = $response->json();
                return response()->json([
                    'error' => $error['error'] ?? 'Failed to create trade'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error creating trade: ' . $e->getMessage());
            return response()->json(['error' => 'Service unavailable'], 503);
        }
    }

    /**
     * Accept an existing trade.
     */
    public function acceptTrade(Request $request, $tradeId)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/'
        ]);

        try {
            // Call Node.js bridge to accept trade on smart contract
            $response = Http::post(env('NODE_URL') . '/escrow/accept-trade', [
                'tradeId' => $tradeId,
                'walletAddress' => $validated['wallet_address']
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Clear trades cache
                $this->clearTradesCache();

                return response()->json([
                    'message' => 'Trade accepted successfully',
                    'transaction_hash' => $result['transactionHash'] ?? null
                ]);
            } else {
                $error = $response->json();
                return response()->json([
                    'error' => $error['error'] ?? 'Failed to accept trade'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error accepting trade: ' . $e->getMessage());
            return response()->json(['error' => 'Service unavailable'], 503);
        }
    }

    /**
     * Cancel an existing trade.
     */
    public function cancelTrade(Request $request, $tradeId)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/'
        ]);

        try {
            // Call Node.js bridge to cancel trade on smart contract
            $response = Http::post(env('NODE_URL') . '/escrow/cancel-trade', [
                'tradeId' => $tradeId,
                'walletAddress' => $validated['wallet_address']
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Clear trades cache
                $this->clearTradesCache();

                return response()->json([
                    'message' => 'Trade cancelled successfully',
                    'transaction_hash' => $result['transactionHash'] ?? null
                ]);
            } else {
                $error = $response->json();
                return response()->json([
                    'error' => $error['error'] ?? 'Failed to cancel trade'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error cancelling trade: ' . $e->getMessage());
            return response()->json(['error' => 'Service unavailable'], 503);
        }
    }

    /**
     * Get trade details by ID.
     */
    public function getTrade($tradeId)
    {
        try {
            // Call Node.js bridge to get trade details
            $response = Http::post(env('NODE_URL') . '/escrow/get-trade', [
                'tradeId' => $tradeId
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json(['error' => 'Trade not found'], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching trade: ' . $e->getMessage());
            return response()->json(['error' => 'Service unavailable'], 503);
        }
    }

    /**
     * Get trading statistics.
     */
    public function getStats()
    {
        $cacheKey = 'escrow_stats';
        $stats = Cache::get($cacheKey);

        if (!$stats) {
            try {
                // Call Node.js bridge to get stats
                $response = Http::post(env('NODE_URL') . '/escrow/get-stats');

                if ($response->successful()) {
                    $stats = $response->json();
                    // Cache for 5 minutes
                    Cache::put($cacheKey, $stats, 300);
                } else {
                    $stats = [
                        'total_trades' => 0,
                        'active_trades' => 0,
                        'total_volume_snt' => 0,
                        'total_volume_avax' => 0
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Error fetching escrow stats: ' . $e->getMessage());
                $stats = [
                    'total_trades' => 0,
                    'active_trades' => 0,
                    'total_volume_snt' => 0,
                    'total_volume_avax' => 0
                ];
            }
        }

        return response()->json($stats);
    }

    /**
     * Clear trades cache.
     */
    private function clearTradesCache()
    {
        // Clear all trade-related cache entries
        $keys = ['escrow_stats'];
        
        // Clear paginated trade caches (assuming max 100 pages)
        for ($start = 0; $start <= 2000; $start += 20) {
            $keys[] = "escrow_trades_{$start}_20";
        }

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}

