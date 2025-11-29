<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

            $message = 'Referral code applied successfully!';

            // ===> Give 1 SNT Bonus (Locked) <===
            // Fix race condition using DB transaction and lockForUpdate
            DB::transaction(function () use ($referredUser, &$message) {
                // Lock the user row to prevent concurrent updates
                $user = User::where('id', $referredUser->id)->lockForUpdate()->first();

                if (!$user->has_received_signup_bonus) {
                    $user->increment('token_balance', 1);
                    $user->increment('locked_balance', 1);
                    $user->update(['has_received_signup_bonus' => true]);

                    Log::info('🎉 Signup bonus granted (LOCKED) to referred user', [
                        'user_id' => $user->id,
                        'bonus_amount' => 1,
                        'new_token_balance' => $user->fresh()->token_balance,
                        'new_locked_balance' => $user->fresh()->locked_balance
                    ]);

                    $message .= ' You received 1 SNT (Locked).';
                }
            });

            Log::info('Referral created', [
                'referrer_id' => $referrer->id,
                'referred_id' => $referredUser->id,
                'referral_id' => $referral->id,
            ]);

            return response()->json(['message' => $message]);
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
            ->withSum('referralRewards', 'reward_tokens') // <-- AJOUTE CETTE LIGNE
            ->orderBy('validated_referrals_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'wallet_address']);

        $top = $top->map(fn ($u) => [
                'name'           => $u->name,
                'wallet_address' => substr($u->wallet_address, 0, 6) . '...' . substr($u->wallet_address, -4),
                'referral_count' => $u->validated_referrals_count,
                // --- CORRECTION ICI ---
                'rewards_earned' => (int) $u->referral_rewards_sum_reward_tokens ?? 0,
            ]);

        return response()->json($top);
    }

    // Dans app/Http/Controllers/ReferralController.php

    public function validateReferral(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        // L'utilisateur qui vient de participer à son premier tournoi (le "filleul")
        $referredUser = User::find($validated['user_id']);

        // On le marque comme éligible pour parrainer à son tour.
        if ($referredUser && !$referredUser->is_eligible_to_refer) {
            $referredUser->update(['is_eligible_to_refer' => true]);
            Log::info('User is now eligible to refer', ['user_id' => $referredUser->id]);
        }

        // Cherche si cet utilisateur a été parrainé
        $referral = Referral::where('referred_id', $validated['user_id'])
            ->where('status', 'pending')
            ->first();

        // S'il n'a pas de parrainage en attente, il n'y a rien de plus à faire pour le système de parrainage.
        if (!$referral) {
            return response()->json(['message' => 'User is now eligible to refer. No pending referral found.'], 200);
        }

        // ====> DÉBUT DE LA NOUVELLE LOGIQUE POUR LE BONUS DU FILLEUL <====
        
        // On vérifie si le filleul n'a pas déjà reçu son bonus et s'il a bien un parrainage.
        if (!$referredUser->has_received_signup_bonus) {
            // Il est venu avec un code, on lui donne son bonus de 1 SNT !
            $referredUser->increment('token_balance', 1);
            $referredUser->update(['has_received_signup_bonus' => true]);

            Log::info('🎉 Signup bonus granted to referred user', [
                'user_id' => $referredUser->id,
                'bonus_amount' => 1,
                'new_token_balance' => $referredUser->fresh()->token_balance
            ]);
        }
        // ====> FIN DE LA NOUVELLE LOGIQUE <====

        // On continue avec la logique existante pour récompenser le PARRAIN
        $referral->update(['status' => 'validated']);
        
        $referrer = $referral->referrer;
        $totalValidated = Referral::where('referrer_id', $referrer->id)
            ->where('status', 'validated')
            ->count();

        $milestones = [ 1 => 1, 3 => 1, 5 => 1, 11 => 1, 50 => 1, 100 => 1 ];

        $alreadyRewarded = \App\Models\ReferralReward::where('referrer_id', $referrer->id)
            ->where('milestone_reached', $totalValidated)
            ->exists();

        if (array_key_exists($totalValidated, $milestones) && !$alreadyRewarded) {
            $rewardAmount = $milestones[$totalValidated];

            \App\Models\ReferralReward::create([
                'referrer_id' => $referrer->id,
                'milestone_reached' => $totalValidated,
                'reward_tokens' => $rewardAmount,
            ]);

            $referrer->increment('token_balance', $rewardAmount);

            Log::info('🎁 Reward granted and token balance updated for referrer', [
                'referrer_id' => $referrer->id,
                'milestone' => $totalValidated,
                'new_token_balance' => $referrer->fresh()->token_balance
            ]);
        }

        return response()->json([
            'message' => 'Referral validated successfully. Referrer and Referred have been rewarded.',
            'referral_id' => $referral->id
        ]);
    }
}