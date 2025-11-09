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
use App\Events\PoolFinishedEvent;
use App\Events\FightCreatedEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;


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
                            'pool_id'         => null,
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
        $security_coefficient = Config('game_settings.security_coefficient');
        Log::info('handlePoolEmitedEvent received this data: '.json_encode($data));
        if (
            empty($data['pool_id']) ||
            empty($data['base_bet']) ||
            empty($data['users']) ||
            empty($data['premove_cids']) ||
            empty($data['pool_salt'])
        ) {
            Log::info('if (empty($data[pool_id]) ||... : ');
            throw new \InvalidArgumentException('Missing required parameters.');
        }

        // Manually cast values to their expected types
        Log::info('manually cast values to their expected types');
        $poolId      = $data['pool_id'];
        $baseBetStr  = $data['base_bet'];
        $baseBet     = Web3Helper::weiToEther($baseBetStr);

        
        //----------------------------------------------------------------------------------

        if (isset($data['users']) && is_string($data['users'])) {
            $users = explode(',', $data['users']);
        } else {
            Log::error('Invalid users format: ' . json_encode($data['users']));
            return response()->json(['error' => 'Invalid users format'], 422);
        }

        if (isset($data['premove_cids']) && is_string($data['premove_cids'])) {
            $premoveCIDs = explode(',', $data['premove_cids']);
        }
        //----------------------------------------------------------------------------------
        

        $poolSalt      = $data['pool_salt'];

        Log::info('handlePoolEmitedEvent pool creation: ');
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
        Log::info(json_decode($usersCollection) );
        //Log::info(json_decode($pool));

        // Store the fetched premove data in the database and associate with users
        foreach ($usersCollection as $index => $user) {
            Log::debug('database cid: ' . $user->preMove->cid.' wallet: ' . $user->wallet_address);
            Log::debug('blockchain cid: ' . $premoveCIDs[$index].' wallet: ' . $user->wallet_address);

            if ($user->preMove->cid !== $premoveCIDs[$index]) {
                Log::error("CID mismatch for user: {$user->wallet_address}");
                
                // Check if CID exists anywhere in blockchain data
                $allIndices = array_keys($premoveCIDs, $user->preMove->cid);
                
                if (!empty($allIndices)) {
                    Log::error("CID exists at different index/indices: " . implode(', ', $allIndices));
                    // Optional: Handle index mismatch (e.g., realign users)
                } else {
                    Log::error("CID not found in any blockchain entries");
                    // Handle missing CID (e.g., mark user as invalid)
                    $user->status = 'invalid';
                    $user->save();
                    //send back user money
                    $returned= $baseBet * $security_coefficient;
                    Web3Helper::sendPayement(env('NODE_URL'), $user->wallet_address, $returned);
                    Log::info("User {$user->wallet_address} has been marked as invalid and refunded {$returned} ETH.");

                    continue;
                }
            }

            $user->balance += $baseBet * $security_coefficient;
            $user->battle_balance = 0;
            $user->preMove->session_first_pool_id = $poolId;
            $user->preMove->save();
            $user->session_started = false; // Reset session_started for new pool
            $user->status = 'available'; // Reset status for new pool
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
    public function processPoolAutoMatch($poolId, $minUsers)
    {
        Log::info("PoolService: Démarrage du matching pour Pool ID: {$poolId}");
        $pool = Pool::findOrFail($poolId);

        // 1. Récupérer les utilisateurs via la relation Eloquent
        $availableUsers = $pool->users()->where('status', 'available')->get();

        // 2. Vérifier si on a assez de joueurs
        if ($availableUsers->count() < $minUsers) {
            Log::info("PoolService: Pas assez d'utilisateurs ({$availableUsers->count()}) pour le Pool {$poolId}.");
            return;
        }

        // 3. Si le nombre est impair, retirer le dernier
        if ($availableUsers->count() % 2 !== 0) {
            $userToPop = $availableUsers->pop();
            Log::info("PoolService: Retrait du user {$userToPop->id} (nombre impair).");
            // On le remet 'available' car il n'a pas joué
            $userToPop->update(['status' => 'available']);
        }

        // 4. Segmenter les utilisateurs en paires (magie de Laravel)
        $userPairs = $availableUsers->chunk(2);

        // 5. Boucler sur les paires et créer les combats
        foreach ($userPairs as $pair) {
            
            // (Double-check, même si on a géré l'impair)
            if ($pair->count() < 2) continue; 

            $user1 = $pair->first();
            $user2 = $pair->last();

            // Verrouiller les utilisateurs
            $user1->update(['status' => 'locked']);
            $user2->update(['status' => 'locked']);

            // 6. CRÉER le combat (sans logique)
            $fight = Fight::create([
                'pool_id'         => $poolId,
                'user1_id'        => $user1->id,
                'user2_id'        => $user2->id,
                'status'          => 'waiting_for_result',
                'base_bet_amount' => $pool->base_bet,
            ]);
            
            Log::info("PoolService: Combat {$fight->id} créé. Déclenchement de FightCreatedEvent.");
            
            // 7. DÉCLENCHER l'événement pour ce combat
            event(new FightCreatedEvent($fight));
        }

        // 8. Déclencher l'événement de fin de pool (une seule fois)
        // Note: Tu devrais peut-être déclencher cet événement
        // seulement quand tous les combats sont 'completed'.
        // Pour l'instant, on garde ta logique.
        event(new PoolFinishedEvent($poolId));

        return ['pool_id' => $poolId, 'status' => 'processed'];
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
