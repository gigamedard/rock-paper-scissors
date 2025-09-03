<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReferralController extends Controller
{
    /**
     * Get referral status for the authenticated user.
     */
    public function getStatus()
    {
        $user = Auth::user();
        
        if (!$user->referral_code) {
            $user->generateReferralCode();
        }

        $stats = $user->getReferralStats();

        return response()->json([
            'code' => $user->referral_code,
            'totalReferrals' => $stats['total'],
            'pendingReferrals' => $stats['pending'],
            'validatedReferrals' => $stats['validated'],
            'totalRewards' => $stats['rewards_earned']
        ]);
    }

    /**
     * Process a referral when a user registers.
     */
    public function processReferral(Request $request)
    {
        $validated = $request->validate([
            'referral_code' => 'required|string|exists:users,referral_code',
            'referred_user_id' => 'required|integer|exists:users,id'
        ]);

        $referrer = User::where('referral_code', $validated['referral_code'])->first();
        $referred = User::find($validated['referred_user_id']);

        if (!$referrer || !$referred) {
            return response()->json(['error' => 'Invalid referral data'], 400);
        }

        // Check if referral already exists
        $existingReferral = Referral::where('referrer_id', $referrer->id)
            ->where('referred_id', $referred->id)
            ->first();

        if ($existingReferral) {
            return response()->json(['error' => 'Referral already exists'], 400);
        }

        // Create the referral
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'status' => 'pending'
        ]);

        Log::info('Referral created', [
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'referral_id' => $referral->id
        ]);

        return response()->json([
            'message' => 'Referral processed successfully',
            'referral_id' => $referral->id
        ]);
    }

    /**
     * Validate a referral (called when user makes first AVAX transaction).
     */
    public function validateReferral(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $referral = Referral::where('referred_id', $validated['user_id'])
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return response()->json(['error' => 'No pending referral found'], 404);
        }

        // Update referral status
        $referral->update(['status' => 'validated']);

        // Trigger reward event (this would be handled by a listener)
        event(new \App\Events\ReferralValidated($referral));

        Log::info('Referral validated', [
            'referral_id' => $referral->id,
            'referrer_id' => $referral->referrer_id,
            'referred_id' => $referral->referred_id
        ]);

        return response()->json([
            'message' => 'Referral validated successfully',
            'referral_id' => $referral->id
        ]);
    }

    /**
     * Get referral leaderboard.
     */
    public function getLeaderboard()
    {
        $topReferrers = User::withCount(['referrals as validated_referrals_count' => function ($query) {
                $query->where('status', 'validated');
            }])
            ->having('validated_referrals_count', '>', 0)
            ->orderBy('validated_referrals_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'wallet_address'])
            ->map(function ($user) {
                return [
                    'name' => $user->name,
                    'wallet_address' => substr($user->wallet_address, 0, 6) . '...' . substr($user->wallet_address, -4),
                    'referral_count' => $user->validated_referrals_count,
                    'rewards_earned' => $user->validated_referrals_count * 100
                ];
            });

        return response()->json($topReferrers);
    }
}

