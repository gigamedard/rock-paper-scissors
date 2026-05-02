<?php

namespace App\Services;

use App\Models\FHist;
use App\Models\User;
use App\Jobs\UploadSessionHistoryJob;
use Illuminate\Support\Facades\Log;

class SessionHistoryService
{
    /**
     * Archives the complete session history for a user by fetching all their FHist records
     * from their very first pool to the end of the session.
     */
    public function archiveSessionHistory(User $user): void
    {
        if (!$user->preMove || !$user->preMove->session_first_pool_id) {
            Log::warning("Cannot archive session for User {$user->id}: No session_first_pool_id found.");
            return;
        }

        $fHists = $this->fetchSessionHistory($user->id, $user->preMove->session_first_pool_id);

        if ($fHists->isEmpty()) {
            Log::info("No history to archive for User {$user->id}.");
            return;
        }

        UploadSessionHistoryJob::dispatch($user->wallet_address, $user->id, $fHists);
    }

    /**
     * Optimized query to fetch all FHist records for a given user from a starting pool ID.
     */
    private function fetchSessionHistory(int $userId, int $firstPoolId)
    {
        // 1. Find the very first FHist ID where this user participated in their starting pool
        $initialRecord = FHist::where('pool_id', $firstPoolId)
            ->where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)->orWhere('user2_id', $userId);
            })
            ->orderBy('id', 'asc')
            ->first();

        if (!$initialRecord) {
            return collect([]);
        }

        // 2. Fetch all subsequent records for this user (Optimization & Bug fix applied here)
        return FHist::where('id', '>=', $initialRecord->id)
            ->where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)->orWhere('user2_id', $userId);
            })
            ->orderBy('id', 'asc')
            ->get();
    }
}
