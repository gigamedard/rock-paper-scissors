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
        return DB::transaction(function () {
            $user = Auth::user();
            if (!$user || !$user->influencer) {
                return response()->json(['error' => 'User is not an influencer'], 404);
            }

            $influencer = Influencer::where('id', $user->influencer->id)->lockForUpdate()->first();

            if (!$influencer || !$influencer->canClaimReward()) {
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
        });
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


    public function getDashboardData(Request $request)
    {
        $user = $request->user();
        $influencer = $user->influencer()->with('stats', 'pool')->first();

        // 1. Vérifier si l'utilisateur est un influenceur
        if (!$influencer) {
            return response()->json(['error' => 'Accès réservé aux influenceurs'], 403);
        }

        // 2. Obtenir le classement (contient le rang)
        $leaderboard = $this->getLeaderboard();
        $myRank = $leaderboard->search(fn($item) => $item['user_id'] === $user->id);

        // 3. Obtenir les stats personnelles
        $myStats = [
            'personalReferrals' => $influencer->stats->referral_count ?? 0,
            'avaxSpent' => $influencer->stats->total_avax_spent ?? 0,
            'conversionRate' => 0, // Placeholder
            'myRank' => $myRank !== false ? $myRank + 1 : 'N/A',
            'hasClaimed' => $influencer->has_claimed,
        ];
        
        // 4. Obtenir les données de tous les pools
        $allPools = InfluencerPool::with(['influencers.stats'])
            ->where('is_active', true)
            ->get()
            ->map(fn($pool) => $this->formatPoolData($pool));
        
        // 5. Isoler le pool de l'influenceur pour l'affichage du haut
        $myPoolData = $allPools->firstWhere('id', $influencer->pool_id);
        if ($myPoolData) {
            $myPoolData['personal_milestone_target'] = $influencer->pool->milestone;
            $myPoolData['personal_referrals_count'] = $myStats['personalReferrals'];
            $myPoolData['personal_progress_percentage'] = $myPoolData['personal_milestone_target'] > 0 ?
                ($myStats['personalReferrals'] / $myPoolData['personal_milestone_target']) * 100 : 0;
            $myPoolData['canClaim'] = $influencer->canClaimReward();
        }

        return response()->json([
            'myStats' => $myStats,
            'myPool' => $myPoolData,
            'allPools' => $allPools,
            'leaderboard' => $leaderboard->values(),
        ]);
    }


    private function formatPoolData(InfluencerPool $pool)
    {
        $totalReferrals = $pool->total_referral_count;
        $progressPercentage = $pool->pool_milestone > 0 ? ($totalReferrals / $pool->pool_milestone) * 100 : 0;
        $eligibleInfluencers = $pool->getEligibleInfluencers()->count();

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'language' => $pool->language,
            'reward_amount' => (float) $pool->reward_amount,
            'pool_milestone' => $pool->pool_milestone,
            'milestone' => $pool->milestone, // Jalon personnel
            'current_referrals' => $totalReferrals,
            'progress_percentage' => $progressPercentage,
            'total_influencers' => $pool->influencers->count(),
            'eligible_influencers' => $eligibleInfluencers,
        ];
    }

    /**
     * Génère le classement des influenceurs.
     */
    private function getLeaderboard()
    {
        return Influencer::select('influencers.*')
            ->leftJoin('influencer_stats', 'influencers.id', '=', 'influencer_stats.influencer_id')
            ->with(['user', 'stats', 'pool'])
            ->where('is_eligible', true)
            ->orderByRaw('COALESCE(influencer_stats.referral_count, 0) DESC')
            ->get()
            ->map(function ($influencer, $index) {
                return [
                    'user_id' => $influencer->user_id,
                    'name' => $influencer->user->name,
                    'pool_name' => $influencer->pool->name ?? 'N/A',
                    'referral_count' => $influencer->stats->referral_count ?? 0,
                    'conversion_rate' => 0, // Placeholder
                    'has_claimed_reward' => $influencer->has_claimed,
                    'rank' => $index + 1,
                ];
            });
    }
    /**
     * Rejoindre le programme influenceur (POUR TEST UNIQUEMENT)
     */
    public function joinTestProgram(Request $request)
    {
        $user = $request->user();
        $lang = $user->language ?? 'en'; // Fallback to english if not set

        // 1. Find or create a language-specific pool
        $pool = InfluencerPool::firstOrCreate(
            ['language' => $lang],
            [
                'name' => 'Influencers (' . strtoupper($lang) . ')',
                'milestone' => 5,          // Objectif perso : 5 parrainages
                'pool_milestone' => 50,    // Objectif global : 50 parrainages
                'reward_amount' => 100,    // 100 AVAX à partager
                'is_active' => true
            ]
        );

        // 2. Ajouter l'utilisateur comme influenceur
        $influencer = Influencer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'pool_id' => $pool->id,
                'is_eligible' => true,
                'has_claimed' => false
            ]
        );

        // 3. Initialiser les stats
        InfluencerStat::firstOrCreate(
            ['influencer_id' => $influencer->id],
            [
                'referral_count' => 0,
                'total_avax_spent' => 0
            ]
        );

        return response()->json(['message' => 'Welcome to the Influencer Program!']);
    }

    /**
     * Submit an influencer application.
     */
    public function apply(Request $request)
    {
        $user = $request->user();

        // Check if already applied
        $existing = \App\Models\InfluencerApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json(['error' => 'You have already applied or are already an influencer.'], 400);
        }

        $validated = $request->validate([
            'pseudo' => 'required|string|max:255',
            'social_links' => 'required|array|min:1',
            'social_links.*.platform' => 'required|string',
            'social_links.*.url' => 'required|url'
        ]);

        $application = \App\Models\InfluencerApplication::create([
            'user_id' => $user->id,
            'pseudo' => $validated['pseudo'],
            'social_links' => $validated['social_links'],
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Application submitted successfully', 'application' => $application]);
    }

    /**
     * Get application status.
     */
    public function getApplicationStatus(Request $request)
    {
        $user = $request->user();
        $application = \App\Models\InfluencerApplication::where('user_id', $user->id)->latest()->first();

        if (!$application) {
            return response()->json(['status' => 'none']);
        }

        return response()->json(['status' => $application->status, 'application' => $application]);
    }
}

