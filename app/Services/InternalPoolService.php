<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InternalPoolService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Process internal pools for a specific base bet.
     * Scans for AVAILABLE users and groups them into new pools.
     *
     * @param float $baseBet
     * @return array
     */
    public function processInternalPools(float $baseBet): array
    {
        $poolSizes = config('pool.size', [5]);
        $targetPoolSize = $poolSizes[0] ?? 5; // Default to first configured size
        
        // Find users compatible with this pool type
        $limit = 1000; // Safety limit to prevent memory issues
        $users = User::with('preMove') // Eager load for notification logic
            ->where('status', 'available')
            ->where('bet_amount', $baseBet)
            ->where('autoplay_active', true)
            ->limit($limit)
            ->get();

        $scanCount = $users->count();
        $createdPools = 0;
        $usersProcessed = 0;

        if ($scanCount < $targetPoolSize) {
            return [
                'message' => "Not enough users to form a pool for base_bet {$baseBet}. Need {$targetPoolSize}, found {$scanCount}.",
                'created_pools' => 0,
                'users_processed' => 0
            ];
        }

        // Chunk users into pools
        $chunks = $users->chunk($targetPoolSize);

        foreach ($chunks as $chunk) {
            // Only create full pools
            if ($chunk->count() < $targetPoolSize) {
                continue;
            }

            DB::transaction(function () use ($chunk, $baseBet, $targetPoolSize, &$createdPools, &$usersProcessed) {
                $pool = Pool::create([
                    'pool_id' => Str::uuid()->toString(), // Internal ID
                    'base_bet' => $baseBet,
                    'pool_size' => $targetPoolSize,
                    'salt' => Str::random(16),
                    'status' => 'from_server_waitting', // Queued for matching
                ]);

                $userIds = $chunk->pluck('id')->toArray();
                
                // Update users
                User::whereIn('id', $userIds)->update([
                    'status' => 'in_pool',
                    'pool_id' => $pool->id,
                    'session_started' => false, // Reset session state for new pool
                ]);

                // Notifications
                foreach ($chunk as $user) {
                    $reason = 'new_session';
                    if ($user->preMove && $user->preMove->current_index > 0) {
                        $reason = 'after_defeat';
                    }
                    $this->notificationService->notifyPoolEntry($user, $pool, $reason);
                }

                // Users are already attached via pool_id update above
                // $pool->users()->attach($userIds);

                $createdPools++;
                $usersProcessed += count($userIds);
            });
        }

        Log::info("InternalPoolService: Created {$createdPools} pools for base_bet {$baseBet} with {$usersProcessed} users.");

        return [
            'message' => "Processed internal pools.",
            'base_bet' => $baseBet,
            'created_pools' => $createdPools,
            'users_processed' => $usersProcessed
        ];
    }
}
