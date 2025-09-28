<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReferralController extends Controller
{
    public function getStatus(Request $request)
    {
        $user = $request->user();

        if (!$user->referral_code) {
            $user->generateReferralCode();
        }

        $stats = $user->getReferralStats();

        return response()->json([
            'code'              => $user->referral_code,
            'totalReferrals'    => $stats['total'],
            'pendingReferrals'  => $stats['pending'],
            'validatedReferrals'=> $stats['validated'],
            'totalRewards'      => $stats['rewards_earned']
        ]);
    }

    public function applyCodeFromAuthUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'referral_code' => 'required|string',
            ]);

            $referredUser = $request->user();
            if (!$referredUser) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $referralCode = strtoupper(trim($validated['referral_code']));

            // Find referrer
            $referrer = User::where('referral_code', $referralCode)->first();
            if (!$referrer) {
                return response()->json(['message' => 'Invalid referral code'], 422);
            }

            // Prevent self-referral
            if ($referrer->id === $referredUser->id) {
                return response()->json(['message' => 'You cannot use your own referral code.'], 422);
            }

            // Prevent duplicate referral
            $existing = Referral::where('referred_id', $referredUser->id)->first();
            if ($existing) {
                return response()->json(['message' => 'A referral code has already been applied.'], 422);
            }

            // Create referral with code
            $referral = Referral::create([
                'referrer_id'   => $referrer->id,
                'referred_id'   => $referredUser->id,
                'status'        => 'pending',
                'referral_code' => $referralCode, // ✅ store code directly
            ]);

            Log::info('Referral created', [
                'referrer_id' => $referrer->id,
                'referred_id' => $referredUser->id,
                'referral_id' => $referral->id,
            ]);

            return response()->json(['message' => 'Referral code applied successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Referral code application failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid referral code or user'], 400);
        }
    }



    public function getLeaderboard()
    {
        $top = User::withCount(['referrals as validated_referrals_count' => function ($q) {
                $q->where('status', 'validated');
            }])
            ->orderBy('validated_referrals_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'wallet_address'])
            ->map(fn ($u) => [
                'name'           => $u->name,
                'wallet_address' => substr($u->wallet_address, 0, 6) . '...' . substr($u->wallet_address, -4),
                'referral_count' => $u->validated_referrals_count,
                'rewards_earned' => $u->validated_referrals_count * 100,
            ]);

        return response()->json($top);
    }
}
