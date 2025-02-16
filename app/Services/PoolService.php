<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\Web3Helper;
use App\Events\UserBalanceUpdated;
use App\Services\HistoricalFightService;

class PoolService
{   



    private $I=true;


    /**
     * Process auto-match: select users, create fights, and update slice table.
     */
    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10)
    {
        DB::transaction(function () use ($betAmount, $instanceNumber, $limit) {
            $sliceData = DB::table('slice_table')
                ->where('instance_number', $instanceNumber)
                ->where('bet_amount', $betAmount)
                ->where('current_instance', true)
                ->first();

            if (!$sliceData) {
                return;
            }

            $lastUserId = $sliceData->last_user_id ?? 0;

            $users = User::where('autoplay_active', true)
                ->where('bet_amount', $betAmount)
                ->where('id', '>', $lastUserId)
                ->where('status', 'available')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($users->count() % 2 !== 0) {
                $users->pop();
            }

            if ($users->isNotEmpty()) {
                $newLastUserId = $users->last()->id;
                DB::table('slice_table')
                    ->where('instance_number', $instanceNumber)
                    ->update(['last_user_id' => $newLastUserId]);

                for ($i = 0; $i < $users->count(); $i += 2) {
                    if (isset($users[$i + 1])) {
                        $fight = Fight::create([
                            'user1_id'        => $users[$i]->id,
                            'user2_id'        => $users[$i + 1]->id,
                            'base_bet_amount' => $betAmount,
                            'status'          => 'waiting_for_result',
                        ]);
                        $fight->handleAutoplayFight();
                    }
                }
            }

            DB::table('slice_table')
                ->where('instance_number', $instanceNumber)
                ->where('bet_amount', $betAmount)
                ->increment('depth');
        });
    }

    /**
     * Select the appropriate slice instance for a given bet amount.
     */
    public function selectSliceInstence($betAmount)
    {
        $depthLimit = config('game_settings.depth_limit');

        DB::transaction(function () use ($betAmount, $depthLimit) {
            $currentInstance = DB::table('slice_table')
                ->where('current_instance', true)
                ->where('bet_amount', $betAmount)
                ->first();

            if (!$currentInstance) {
                return;
            }

            if ($currentInstance->depth >= $depthLimit) {
                $outdatedInstance = DB::table('slice_table')
                    ->where('instance_number', '!=', $currentInstance->instance_number)
                    ->where('bet_amount', $betAmount)
                    ->orderBy('updated_at', 'asc')
                    ->first();

                if ($outdatedInstance) {
                    DB::table('slice_table')
                        ->where('id', $currentInstance->id)
                        ->update(['current_instance' => false]);

                    DB::table('slice_table')
                        ->where('id', $outdatedInstance->id)
                        ->update(['current_instance' => true, 'depth' => 0]);
                }
            }
            
            $this->processAutoMatch($betAmount, $currentInstance->instance_number);
        });
    }

    /**
     * Process all bet amounts.
     */
    public function selectSliceInstenceForAllBetAmount()
    {
        $betAmounts = config('game_settings.bet_amounts');

        foreach ($betAmounts as $betAmount) {
            $this->selectSliceInstence($betAmount);
        }
    }

    /**
     * Handle the pool emitted event.
     * Expects keys: pool_id, base_bet, users, premove_cids, pool_salt.
     */
    public function handlePoolEmitedEvent(array $data)
    {
        if (
            empty($data['pool_id']) ||
            empty($data['base_bet']) ||
            empty($data['users']) ||
            empty($data['premove_cids']) ||
            empty($data['pool_salt'])
        ) {
            throw new \InvalidArgumentException('Missing required parameters.');
        }

        // Manually cast values to their expected types
        $poolId      = $data['pool_id'];
        $baseBetStr  = $data['base_bet'];
        $baseBet     = Web3Helper::weiToEther($baseBetStr);

        // Convert the users string into an array
        $users = json_decode($data['users'], true);

        // Check if json_decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle the error, e.g., return a response or log the error
            return response()->json(['error' => 'Invalid JSON data'], 422);
        }
        
        $premoveCIDs   = json_decode($data['premove_cids'], true);
        $poolSalt      = $data['pool_salt'];


        $pool = Pool::create([
            'pool_id' => $poolId,
            'base_bet' => $baseBet,
            'salt' => $poolSalt,
            'pool_size' => count($users),
        ]);

        // Ensure the arrays have at least 2 elements
        if (count($users) < 2 || count($premoveCIDs) < 2) {
            return response()->json(['error' => 'Arrays must have at least 2 elements'], 422);
        }

        // Fetch all users in one query
        $usersCollection = User::whereIn('wallet_address', $users)->get();

        $u = User::all();
        dump($usersCollection);
        dump($pool);

        // Store the fetched premove data in the database and associate with users
        foreach ($usersCollection as $index => $user) {
            if ($user->preMove->cid !== $premoveCIDs[$index]) {
                Log::error("CID mismatch for user: {$user->wallet_address}");
                continue;
            }
        }

        try {
            // Mark users as in_pool (not available for other pools)
            $usersCollection->each(function ($user) use ($poolId) {
                $user->update([
                    'status'  => 'in_pool',
                    'pool_id' => $poolId,
                ]);

                $user->save();
            });
            log::info('processPoolAutoMatch');
            $this->processPoolAutoMatch($poolId);
        } catch (\Exception $e) {
            Log::error('Error processing $pool = Pool::create: ' . $e->getMessage());
            throw $e;
        }

        return ['pool_id' => $data['pool_id'], 'status' => 'processed'];
    }

    /**
     * Process pool auto-match: deduct balances, sort users, and create fights.
     */
    public function processPoolAutoMatch(int $poolId)
    {   log::info('====>processPoolAutoMatch start');
        $pool = Pool::with(['users' => function ($query) {
            $query->where('status', 'in_pool')->orderBy('id');
        }])->where('pool_id', $poolId)->firstOrFail();

        $percentageLimit = config('pool.percentage_limit_of_pool_size');
      
        $minUsers = ceil($pool->pool_size * $percentageLimit);
        if($minUsers<2) $minUsers=2;

          log::info('====>processPoolAutoMatch minUsers: '.$minUsers);
        $availableUsers = User::where('pool_id', $poolId)->where('status', 'in_pool')->get();
        
        // Iterate matching rounds until available users fall below the percentage threshold or there is not enough for a pair
        $this->hasSufficientUsersForMatch($availableUsers->count(),$minUsers);
        while ($this->I) {
           
                $pool = Pool::with(['users' => function ($query) {
                    $query->where('status', 'in_pool')->orderBy('id');
                }])->where('pool_id', $poolId)->firstOrFail();
        
                $availableUsers = $pool->users;
                Log::info('====> processPoolAutoMatch while ($availableUsers->count() >= $minUsers && $availableUsers->count() >= 2) {: ' . $availableUsers->count() . ' users available for pool ' . $poolId);
        
                foreach ($availableUsers as $user) {
                    if ($user->balance >= $pool->base_bet) {
                        $user->balance        -= $pool->base_bet;
                        $user->battle_balance += $pool->base_bet;
                        $user->save();
                        Log::info(' event(new UserBalanceUpdated($user));');
                        event(new UserBalanceUpdated($user));
                    } else {
                        $availableUsers = $availableUsers->reject(fn($u) => $u->id === $user->id);
                    }
                }
        
                // Sort users using a helper with salt
                Log::info(' $sortedAddresses = Web3Helper::sortAddressesWithSalt($availableUsers->pluck(\'wallet_address\')->toArray(), $pool->salt);');
                $sortedAddresses = Web3Helper::sortAddressesWithSalt(
                    $availableUsers->pluck('wallet_address')->toArray(),
                    $pool->salt
                );
                log::info('====>processPoolAutoMatch $sortedAddresses: ' . json_encode($sortedAddresses));
                $availableUsers = $availableUsers->sortBy(function ($user) use ($sortedAddresses) {
                    return array_search($user->wallet_address, $sortedAddresses);
                })->values();
        
                if ($availableUsers->count() % 2 !== 0) {
                    $availableUsers->pop();
                }
                log::info('====>processPoolAutoMatch $availableUsers: '.Json_encode($availableUsers));
                if ($availableUsers->isNotEmpty()) {
                    log::info('====>processPoolAutoMatch $availableUsers->isNotEmpty()');
                    for ($i = 0; $i < $availableUsers->count(); $i += 2) {
                        Log::info(' $fight = Fight::create([ count'.$availableUsers->count().');');
        
                        //log fight parameters
                        Log::info('user1_id: '.$availableUsers[$i]->id);
                        Log::info('user2_id: '.$availableUsers[$i + 1]->id);
                        Log::info('base_bet_amount: '.$pool->base_bet);
                        Log::info('status: waiting_for_result');
                        Log::info('pool_id: '.$poolId);
                        
        
                        $fight = Fight::create([
                            'user1_id'        => $availableUsers[$i]->id,
                            'user2_id'        => $availableUsers[$i + 1]->id,
                            'base_bet_amount' => $pool->base_bet,
                            'status'          => 'waiting_for_result',
                            'pool_id'         => $poolId,
                        ]);
                        Log::info(' fight:);'.$fight);
                        $fight->handlePoolAutoplayFight($pool->base_bet, $pool->pool_size);
                    }
                }
                $this->hasSufficientUsersForMatch($availableUsers->count(),$minUsers);
        }


        // get the pool fights and archive them
        $historicalService = new HistoricalFightService();
        $cid = $historicalService->archivePoolFights($poolId);
        Log::info("Minimum users required to run matches: {$minUsers}");
        //log the cid
        Log::info('cid: '.$cid);
        dump($cid);
        return ['pool_id' => $poolId,'cid' => $cid, 'status' => 'processed'];
        
    }

    private function hasSufficientUsersForMatch($avUs, $minUs){
        
        if ($avUs <= $minUs) {
            $this->I=false;
            return;
        }
        elseif ($avUs <= 2) {
            $this->I=false;
            return;
        }
        else{
            $this->I=true;
        }
        log::info('====>processPoolAutoMatch hasSufficientUsersForMatch aivailable users :adult:'.$avUs.'minimum user:➖ '.$minUs.'should iterate ? '.$this->I);
    }
}
