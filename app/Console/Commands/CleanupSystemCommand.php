<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Pool;
use App\Models\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\UserTracker;

class CleanupSystemCommand extends Command
{
    protected $signature = 'battle:cleanup {--force : Skip confirmation}';
    protected $description = 'Clean up ghost users, orphaned battle balances, and stalled pools/batches';

    public function handle()
    {
        $this->info("Starting System Integrity Cleanup...");

        // 1. Ghost Users (in_pool or in_fight status but no pool_id)
        $ghosts = User::whereIn('status', ['in_pool', 'in_fight'])->whereNull('pool_id')->get();
        if ($ghosts->count() > 0) {
            $this->warn("Found {$ghosts->count()} ghost users. Resetting to available.");
            foreach ($ghosts as $ghost) {
                $ghost->update(['status' => 'available']);
                UserTracker::warning("Cleanup: Reset ghost user {$ghost->wallet_address} (ID: {$ghost->id}) to available.");
            }
        }

        // 2. Batched Pools without Batch (stuck pools)
        $batchedPools = Pool::where('status', 'batched')->get();
        foreach ($batchedPools as $pool) {
            $batchExists = Batch::where('first_pool_id', '<=', $pool->id)
                ->where('last_pool_id', '>=', $pool->id)
                ->exists();
            
            if (!$batchExists) {
                $this->warn("Pool {$pool->id} is marked as batched but no active batch covers it. Resetting to waitting.");
                $pool->update(['status' => 'from_server_waitting']);
                UserTracker::warning("Cleanup: Reset stuck batched pool {$pool->id} to from_server_waitting.");
            }
        }

        // 3. Under-populated pools (waiting but have fewer active users than pool_size)
        //    These are orphaned pools that will NEVER fill up — must be closed immediately.
        $targetPoolSize = config('pool.size')[0] ?? 5;
        $baseBet = (float) collect(config('pool.base_bet', [0.01]))->min();

        $waitingPools = Pool::where('status', 'from_server_waitting')->get();
        foreach ($waitingPools as $pool) {
            $activeUsers = User::where('pool_id', $pool->id)
                ->where('status', 'in_pool')
                ->get();

            $isUnderPopulated = $activeUsers->count() > 0 && $activeUsers->count() < $targetPoolSize;
            $isOldAndEmpty    = $activeUsers->count() === 0 && $pool->created_at->lt(now()->subMinutes(5));
            $isStalled        = $pool->created_at->lt(now()->subHour());

            if ($isUnderPopulated || $isOldAndEmpty || $isStalled) {
                $reason = $isUnderPopulated ? "under-populated ({$activeUsers->count()}/{$targetPoolSize})"
                        : ($isOldAndEmpty ? 'empty+stale' : 'stalled >1h');

                $this->warn("Pool {$pool->id} ({$reason}). Refunding {$activeUsers->count()} players and closing.");

                DB::transaction(function () use ($pool, $activeUsers, $baseBet) {
                    foreach ($activeUsers as $user) {
                        // Refund battle_balance back to main balance
                        $user->balance        += $user->battle_balance;
                        $user->battle_balance  = 0;
                        $user->status          = 'available';
                        $user->pool_id         = null;
                        $user->bet_amount      = $baseBet; // reset to 0.01
                        $user->save();
                        UserTracker::info("Cleanup: Refunded & released User {$user->id} ({$user->wallet_address}) from orphan pool {$pool->id}. bet_amount reset to {$baseBet}.", ['pool_id' => $pool->id]);
                    }
                    $pool->update(['status' => 'from_server_finished']);
                });

                UserTracker::warning("Cleanup: Closed orphan pool {$pool->id} ({$reason}).");
            }
        }


        // 4. Stalled Batches (processing for more than 30 minutes, all pools finished, or invalid range)
        $stalledBatches = Batch::where('status', '!=', 'finished')->get();
        foreach ($stalledBatches as $batch) {
            $poolQuery = Pool::whereBetween('id', [$batch->first_pool_id, $batch->last_pool_id])
                ->where('base_bet', $batch->base_bet);

            $anyMatchingPools = (clone $poolQuery)->exists();
            $allFinished = $anyMatchingPools && (clone $poolQuery)->where('status', '!=', 'from_server_finished')->count() === 0;

            if (!$anyMatchingPools) {
                $this->warn("Batch {$batch->id} has no matching pools of tier {$batch->base_bet} in its range [{$batch->first_pool_id}-{$batch->last_pool_id}]. Deleting stale batch.");
                $batch->delete();
                UserTracker::info("Cleanup: Deleted invalid batch {$batch->id} (no matching pools in range).");
                continue;
            }

            if ($allFinished) {
                $this->warn("Batch {$batch->id} has all its matching pools finished. Deleting stale batch.");
                $batch->delete();
                UserTracker::info("Cleanup: Deleted stale batch {$batch->id} (all matching pools finished).");
                continue;
            }

            if ($batch->status === 'processing' && $batch->updated_at < now()->subMinutes(30)) {
                $this->warn("Found stalled batch {$batch->id}. Resetting to waiting.");
                $batch->update(['status' => 'waiting']);
                UserTracker::warning("Cleanup: Reset stalled batch {$batch->id} to waiting.");
            }
        }

        $this->info("Cleanup completed successfully!");
        return 0;
    }
}
