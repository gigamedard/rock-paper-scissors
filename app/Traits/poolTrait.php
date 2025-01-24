<?php

namespace App\Traits;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PreMove;
use App\Models\Batch;

trait poolTrait
{
    public function handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt)
    {
        // Validate input data
        if (empty($poolId) || empty($baseBet) || empty($users) || empty($premoveCIDs) || empty($poolSalt)) {
            throw new \InvalidArgumentException('All parameters must be non-empty.');
        }
    
        // Log the number of users and pre-move CIDs
        Log::info('Number of users: ' . count($users));
        Log::info('Number of pre-move CIDs: ' . count($premoveCIDs));

        // Start database transaction
        DB::transaction(function () use ($poolId, $baseBet, $users, $poolSalt, $premoveCIDs) {
            try {
                // Create the pool
                $pool = Pool::create([
                    'pool_id' => $poolId,
                    'base_bet' => $baseBet,
                    'salt' => $poolSalt,
                    'pool_size' => count($users),
                ]);
    
                // Fetch all users in one query
                $usersCollection = User::whereIn('wallet_address', $users)->get();
    
                // Store the fetched premove data in the database and associate with users
                /*
                foreach ($usersCollection as $index => $user) {
                    // Ensure the CID matches the stored CID for the current user
                    if ($user->preMove->cid !== $premoveCIDs[$index]) {
                        Log::error("CID mismatch for user: {$user->wallet_address}");
                        continue;
                    }

                }
                */
                // Mark users as in_pool (not available for other pools)
                $usersCollection->each(function ($user) {
                    $user->update(['status' => 'in_pool']);
                });
    
                // Attach users to the pool
                $pool->users()->attach($usersCollection->pluck('id')->toArray());
    
                // Proceed to batch processing
                $this->processBatch();
            } catch (\Exception $e) {
                // Log the error and re-throw the exception
                Log::error('Error in handlePoolEmittedEvent: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}
