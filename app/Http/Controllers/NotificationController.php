<?php

namespace App\Http\Controllers;

use App\Models\GameNotification;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Poll for notifications and game state updates.
     * This endpoint is called every few seconds by the frontend.
     */
    public function poll(Request $request)
    {
        $headers = [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
        
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // 1. Get Unread Notifications
        $notifications = GameNotification::where('user_id', $user->id)
                                         ->whereNull('read_at')
                                         ->orderBy('created_at', 'asc')
                                         ->get();

        // Mark as read immediately (or let frontend acknowledge, but auto-read is simpler for polling)
        // We will mark them as read so they don't appear in next poll
        // If we want persistence in UI history, we might want a separate "acknowledge" endpoint or just return latest 5 regardless of read status
        // For "Toast" behavior, fetching unread and marking read is standard.
        GameNotification::whereIn('id', $notifications->pluck('id'))->update(['read_at' => now()]);

        // 2. Get Current User State (for robust sync)
        
        // Active Pool?
        $activePool = null;
        // Check if user is in a pre_move that is LINKED to a pool instance?
        // Or if user is in a 'pending' Referral?
        // Let's check if they have meaningful state to report.
        
        // Example: Check if they are in a pool (maybe via PreMove -> pool_id if we had that, or implicit logic)
        // For now, let's just return balance which is critical.
        $balance = $user->balance;
        $tokenBalance = $user->token_balance;

        // 3. Assemble Response
        $response = [
            'notifications' => $notifications, // Array of event objects
            'state' => [
                'user_id' => $user->id,
                'wallet_address' => $user->wallet_address,
                'balance_eth' => $balance,
                'balance_snt' => $tokenBalance,
                'is_admin' => $user->is_admin,
                // Add more state here as needed:
                // 'active_pool_id' => ...
            ]
        ];

        return response()->json($response, 200, $headers);
    }
}
