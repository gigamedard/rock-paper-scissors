<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\InfluencerApplication;
use App\Models\Influencer;
use App\Models\InfluencerPool;
use App\Models\InfluencerStat;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function getStats(Request $request)
    {

        $totalUsers = \App\Models\User::count();
        $activeBots = \App\Models\User::where('autoplay_active', true)->where('is_online', true)->count();
        $totalFights = \App\Models\Fight::count();
        $totalVolume = \App\Models\Fight::sum('base_bet_amount');
        
        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $totalFees = \App\Helpers\Web3Helper::getContractHouseBalance($nodeUrl);

        $activePools = \App\Models\Pool::where('status', 'active')->count();
        
        $recentFights = \App\Models\Fight::with(['user1', 'user2'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'kpis' => [
                'total_users' => $totalUsers,
                'active_bots' => $activeBots,
                'total_fights' => $totalFights,
                'total_volume' => round($totalVolume, 4),
                'total_fees' => round($totalFees, 4),
                'active_pools' => $activePools,
            ],
            'recent_fights' => $recentFights
        ]);
    }

    public function getUsers(Request $request)
    {

        $users = \App\Models\User::orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($users);
    }

    public function getApplications(Request $request)
    {

        $applications = InfluencerApplication::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($applications);
    }

    public function approveApplication(Request $request, $id)
    {

        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['error' => 'Invalid application ID'], 400);
        }

        $application = InfluencerApplication::findOrFail((int)$id);
        
        if ($application->status !== 'pending') {
            return response()->json(['error' => 'Application is not pending'], 400);
        }

        DB::transaction(function () use ($application) {
            $application->update(['status' => 'approved']);

            // Assign to default pool (or let admin choose later, for now default)
            $pool = InfluencerPool::firstOrCreate(
                ['name' => 'Global Influencers'],
                [
                    'language' => 'en',
                    'milestone' => 5,
                    'pool_milestone' => 50,
                    'reward_amount' => 100,
                    'is_active' => true
                ]
            );

            $influencer = Influencer::create([
                'user_id' => $application->user_id,
                'pool_id' => $pool->id,
                'is_eligible' => true,
                'has_claimed' => false
            ]);

            InfluencerStat::create([
                'influencer_id' => $influencer->id,
                'referral_count' => 0,
                'total_avax_spent' => 0
            ]);
        });

        return response()->json(['message' => 'Application approved successfully']);
    }

    public function rejectApplication(Request $request, $id)
    {

        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['error' => 'Invalid application ID'], 400);
        }

        $application = InfluencerApplication::findOrFail((int)$id);
        $application->update(['status' => 'rejected']);

        return response()->json(['message' => 'Application rejected']);
    }

    public function updateUserStatus(Request $request, $id)
    {
        $user = \App\Models\User::findOrFail((int)$id);
        
        $request->validate([
            'status' => 'required|string|in:available,stopped,in_pool,in_fight'
        ]);

        $user->status = $request->input('status');
        $user->save();

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => $user
        ]);
    }
}
