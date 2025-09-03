<?php

namespace App\Http\Controllers;

use App\Models\Influencer;
use App\Models\InfluencerPool;
use App\Models\InfluencerStat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InfluencerController extends Controller
{
    /**
     * Get influencer stats for the authenticated user.
     */
    public function getStats()
    {
        $user = Auth::user();
        $influencer = $user->influencer;

        if (!$influencer) {
            return response()->json(['error' => 'User is not an influencer'], 404);
        }

        $stats = $influencer->stats;
        $pool = $influencer->pool;
        $poolTotalReferrals = $pool->total_referral_count;

        return response()->json([
            'referralCount' => $stats ? $stats->referral_count : 0,
            'poolProgress' => $poolTotalReferrals,
            'rewardPoolSize' => $pool->reward_amount,
            'isEligible' => $influencer->is_eligible,
            'poolName' => $pool->name,
            'personalGoal' => $pool->milestone,
            'poolGoal' => $pool->pool_milestone,
            'canClaim' => $influencer->canClaimReward()
        ]);
    }

    /**
     * Create a new influencer pool (admin only).
     */
    public function createPool(Request $request)
    {
        // This would typically have admin middleware
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|max:255',
            'milestone' => 'required|integer|min:1',
            'pool_milestone' => 'required|integer|min:1',
            'reward_amount' => 'required|numeric|min:0'
        ]);

        $pool = InfluencerPool::create($validated);

        return response()->json([
            'message' => 'Influencer pool created successfully',
            'pool' => $pool
        ]);
    }

    /**
     * Add a user to an influencer pool (admin only).
     */
    public function addInfluencer(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'pool_id' => 'required|integer|exists:influencer_pools,id'
        ]);

        // Check if user is already an influencer in this pool
        $existingInfluencer = Influencer::where('user_id', $validated['user_id'])
            ->where('pool_id', $validated['pool_id'])
            ->first();

        if ($existingInfluencer) {
            return response()->json(['error' => 'User is already in this pool'], 400);
        }

        DB::transaction(function () use ($validated) {
            // Create influencer record
            $influencer = Influencer::create($validated);

            // Create initial stats record
            InfluencerStat::create([
                'influencer_id' => $influencer->id,
                'referral_count' => 0,
                'total_avax_spent' => 0
            ]);
        });

        return response()->json(['message' => 'Influencer added successfully']);
    }

    /**
     * Update influencer eligibility (admin only).
     */
    public function updateEligibility(Request $request, $influencerId)
    {
        $validated = $request->validate([
            'is_eligible' => 'required|boolean'
        ]);

        $influencer = Influencer::findOrFail($influencerId);
        $influencer->update(['is_eligible' => $validated['is_eligible']]);

        return response()->json(['message' => 'Eligibility updated successfully']);
    }

    /**
     * Update influencer stats (called by system when referrals are validated).
     */
    public function updateStats(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'referral_increment' => 'integer|min:0',
            'avax_amount' => 'numeric|min:0'
        ]);

        $user = User::find($validated['user_id']);
        $influencer = $user->influencer;

        if (!$influencer) {
            return response()->json(['error' => 'User is not an influencer'], 404);
        }

        $stats = $influencer->stats;
        if (!$stats) {
            $stats = InfluencerStat::create([
                'influencer_id' => $influencer->id,
                'referral_count' => 0,
                'total_avax_spent' => 0
            ]);
        }

        // Update stats
        if (isset($validated['referral_increment'])) {
            $stats->incrementReferralCount($validated['referral_increment']);
        }

        if (isset($validated['avax_amount'])) {
            $stats->addAvaxSpent($validated['avax_amount']);
        }

        Log::info('Influencer stats updated', [
            'influencer_id' => $influencer->id,
            'user_id' => $user->id,
            'new_referral_count' => $stats->referral_count,
            'new_avax_total' => $stats->total_avax_spent
        ]);

        return response()->json([
            'message' => 'Stats updated successfully',
            'stats' => $stats
        ]);
    }

    /**
     * Claim influencer reward.
     */
    public function claimReward()
    {
        $user = Auth::user();
        $influencer = $user->influencer;

        if (!$influencer) {
            return response()->json(['error' => 'User is not an influencer'], 404);
        }

        if (!$influencer->canClaimReward()) {
            return response()->json(['error' => 'Not eligible to claim reward'], 400);
        }

        // Mark as claimed
        $influencer->update(['has_claimed' => true]);

        // Calculate reward amount
        $pool = $influencer->pool;
        $eligibleCount = $pool->getEligibleInfluencers()->count();
        $rewardAmount = $eligibleCount > 0 ? $pool->reward_amount / $eligibleCount : 0;

        // This would trigger a smart contract interaction to transfer the reward
        event(new \App\Events\InfluencerRewardClaimed($influencer, $rewardAmount));

        return response()->json([
            'message' => 'Reward claimed successfully',
            'amount' => $rewardAmount
        ]);
    }

    /**
     * Get all influencer pools.
     */
    public function getPools()
    {
        $pools = InfluencerPool::with(['influencers.stats'])
            ->where('is_active', true)
            ->get()
            ->map(function ($pool) {
                return [
                    'id' => $pool->id,
                    'name' => $pool->name,
                    'language' => $pool->language,
                    'milestone' => $pool->milestone,
                    'pool_milestone' => $pool->pool_milestone,
                    'reward_amount' => $pool->reward_amount,
                    'total_referrals' => $pool->total_referral_count,
                    'influencer_count' => $pool->influencers->count(),
                    'eligible_count' => $pool->getEligibleInfluencers()->count()
                ];
            });

        return response()->json($pools);
    }
}

