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
    private function checkAdmin($user)
    {
        if (!$user || !$user->is_admin) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function getApplications(Request $request)
    {
        $this->checkAdmin($request->user());

        $applications = InfluencerApplication::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($applications);
    }

    public function approveApplication(Request $request, $id)
    {
        $this->checkAdmin($request->user());

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
        $this->checkAdmin($request->user());

        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['error' => 'Invalid application ID'], 400);
        }

        $application = InfluencerApplication::findOrFail((int)$id);
        $application->update(['status' => 'rejected']);

        return response()->json(['message' => 'Application rejected']);
    }
}
