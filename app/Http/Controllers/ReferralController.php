<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReferralController extends Controller
{
    /**
     * Get referral status for the authenticated user.
     */
    public function getStatus(Request $request)
    {
        $user = $request->user();

        if (!$user->referral_code) {
            $user->generateReferralCode();
        }

        $stats = $user->getReferralStats();

        return response()->json([
            'code'             => $user->referral_code,
            'totalReferrals'   => $stats['total'],
            'pendingReferrals' => $stats['pending'],
            'validatedReferrals' => $stats['validated'],
            'totalRewards'     => $stats['rewards_earned']
        ]);
    }

    /**
     * Apply a referral code for the authenticated user.
     */
    public function applyCodeFromAuthUser(Request $request)
    {
        $validated = $request->validate([
            'referral_code' => 'required|string|exists:users,referral_code',
        ]);

        $referredUser = $request->user();

        // Prevent multiple referrals
        $existingReferral = Referral::where('referred_id', $referredUser->id)->first();
        if ($existingReferral) {
            return response()->json(['message' => 'A referral code has already been applied to your account.'], 422);
        }

        $referrer = User::where('referral_code', $validated['referral_code'])->first();

        // Prevent self-referral
        if ($referrer->id === $referredUser->id) {
            return response()->json(['message' => 'You cannot use your own referral code.'], 422);
        }

        // Create the referral
        $referral = Referral::create([
            'referrer_id'   => $referrer->id,
            'referred_id'   => $referredUser->id,
            'status'        => 'pending',
            'referral_code' => $validated['referral_code']
        ]);

        Log::info('Referral created', [
            'referrer_id' => $referrer->id,
            'referred_id' => $referredUser->id,
            'referral_id' => $referral->id
        ]);

        return response()->json(['message' => 'Referral code applied successfully!']);
    }

    /**
     * Validate a referral (e.g. first AVAX transaction).
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

        $referral->update(['status' => 'validated']);
        event(new \App\Events\ReferralValidated($referral));

        return response()->json([
            'message' => 'Referral validated successfully',
            'referral_id' => $referral->id
        ]);
    }

    /**
     * Leaderboard of referrals.
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
                    'name'           => $user->name,
                    'wallet_address' => substr($user->wallet_address, 0, 6) . '...' . substr($user->wallet_address, -4),
                    'referral_count' => $user->validated_referrals_count,
                    'rewards_earned' => $user->validated_referrals_count * 100
                ];
            });

        return response()->json($topReferrers);
    }
}
