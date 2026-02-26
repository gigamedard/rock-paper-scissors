<?php

namespace App\Services;

use App\Events\PoolFinishedEvent;
use App\Events\UserBalanceUpdated;
use App\Events\SessionFinishedEvent;
use App\Helpers\Web3Helper;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PoolLifecycleService
{
    protected $web3Helper;

    public function __construct(Web3Helper $web3Helper)
    {
        $this->web3Helper = $web3Helper;
    }

    public function handlePoolEmitedEvent(array $data)
    {
        $this->validatePoolEmittedEventData($data);

        $pool = $this->createPoolFromEvent($data);

        $blockchainUsers = explode(',', $data['users']);
        $premoveCIDs = explode(',', $data['premove_cids']);

        // Process directly using the event arrays
        $this->processUsersForPool($blockchainUsers, $premoveCIDs, $pool->id, $pool->base_bet);

        $pool->status = 'from_server_waitting';
        $pool->save();

        // $this->processPoolAutoMatch($pool->id);

        return ['pool_id' => $data['pool_id'], 'status' => 'queued_for_batch_processing'];
    }

    public function processPoolAutoMatch(int $poolId)
    {
        $pool = Pool::with(['users' => function ($query) {
            $query->where('status', 'in_pool')->orderBy('id');
        }])->findOrFail($poolId);

        $minUsers = ceil($pool->pool_size * config('pool.percentage_limit_of_pool_size'));
        if ($minUsers < 2) {
            $minUsers = 2;
        }

        $maxIterations = 100; // Safety limit
        $iterations = 0;
        
        while ($this->hasSufficientUsersForMatch($pool->users->count(), $minUsers) && $iterations < $maxIterations) {
            $this->executeMatchingRound($pool);
            $pool->load('users'); // Refresh the users collection
            $iterations++;
        }
        
        if ($iterations >= $maxIterations) {
            Log::warning("Pool {$pool->id} reached maximum iterations ({$maxIterations}). Breaking loop.");
        }

        $this->finishPool($pool);

        // Update pool status so it isn't picked up by future `from_server_waitting` queries
        $pool->status = 'from_server_finished';
        $pool->save();

        event(new PoolFinishedEvent($poolId));
    }

    private function validatePoolEmittedEventData(array $data)
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
    }

    private function createPoolFromEvent(array $data): Pool
    {
        return Pool::create([
            'pool_id' => $data['pool_id'],
            'base_bet' => $this->web3Helper->weiToEther($data['base_bet']),
            'salt' => $data['pool_salt'],
            'pool_size' => count(explode(',', $data['users'])),
        ]);
    }

    private function processUsersForPool(array $blockchainUsers, array $premoveCIDs, $poolId, $baseBet)
    {
        foreach ($blockchainUsers as $index => $walletAddress) {
            $expectedCid = $premoveCIDs[$index] ?? null;

            // Find the user in our database
            $user = User::where('wallet_address', $walletAddress)->first();

            // If the user doesn't exist OR their CID doesn't match what the blockchain gave us
            if (!$user || !$user->preMove || $user->preMove->cid !== $expectedCid) {
                $this->handleCidMismatch($walletAddress, $expectedCid, $user);
                continue;
            }

            // Valid user, proceed
            $user->balance += $baseBet * config('game_settings.security_coefficient');
            $user->battle_balance = 0;
            $user->preMove->session_first_pool_id = $poolId;
            $user->preMove->save();
            $user->session_started = false;
            $user->status = 'in_pool';
            $user->pool_id = $poolId;
            $user->save();
        }
    }

    private function handleCidMismatch(string $walletAddress, ?string $expectedCid, ?User $user)
    {
        $foundCid = ($user && $user->preMove) ? $user->preMove->cid : 'None/Not Found in DB';
        Log::error("Validation failed for user: {$walletAddress}. Expected CID in blockchain: {$expectedCid}, Found in DB: {$foundCid}");
        
        if ($user) {
            $user->status = 'invalid';
            $user->save();
        }
        
        // Trigger automated refund mapped to the node.js endpoint reading exact balances on-chain
        Web3Helper::refundUsers(env('NODE_URL'), [$walletAddress]);
        Log::info("Wallet {$walletAddress} has been marked as invalid and refunded via smart contract mapping.");
    }

    private function executeMatchingRound(Pool $pool)
    {
        $availableUsers = $pool->users;

        foreach ($availableUsers as $user) {
            if ($user->balance >= $pool->base_bet) {
                if (!$user->session_started) {
                    $user->session_start_balance = $user->balance;
                    $user->session_start_battle_balance = $user->battle_balance;
                    $user->session_started = true;
                }
                $user->balance -= $pool->base_bet;
                $user->battle_balance += $pool->base_bet;
                $user->save();
                event(new UserBalanceUpdated($user));
            } else {
                $availableUsers = $availableUsers->reject(fn ($u) => $u->id === $user->id);
            }
        }

        $sortedAddresses = $this->web3Helper->sortAddressesWithSalt(
            $availableUsers->pluck('wallet_address')->toArray(),
            $pool->salt
        );

        $availableUsers = $availableUsers->sortBy(function ($user) use ($sortedAddresses) {
            return array_search($user->wallet_address, $sortedAddresses);
        })->values();

        if ($availableUsers->count() % 2 !== 0) {
            $availableUsers->pop();
        }

        for ($i = 0; $i < $availableUsers->count(); $i += 2) {
            $fight = Fight::create([
                'user1_id' => $availableUsers[$i]->id,
                'user2_id' => $availableUsers[$i + 1]->id,
                'base_bet_amount' => $pool->base_bet,
                'status' => 'waiting_for_result',
                'pool_id' => $pool->id,
            ]);
            $fight->handlePoolAutoplayFight($pool->base_bet, $pool->pool_size);

            // NOTIFICATION: Battle Started
            foreach ([$availableUsers[$i], $availableUsers[$i+1]] as $combatant) {
                \App\Models\GameNotification::create([
                    'user_id' => $combatant->id,
                    'type' => 'BATTLE_STARTED',
                    'data' => [
                        'fight_id' => $fight->id,
                        'opponent_id' => ($combatant->id == $fight->user1_id) ? $fight->user2_id : $fight->user1_id,
                        'pool_id' => $pool->id
                    ]
                ]);
            }
        }
    }

    private function hasSufficientUsersForMatch($userCount, $minUsers): bool
    {
        return $userCount >= $minUsers && $userCount >= 2;
    }

    private function finishPool(Pool $pool)
    {
        // Retrieve all users who participated in the pool via fights
        $userIds = Fight::where('pool_id', $pool->id)
            ->get()
            ->flatMap(function ($fight) {
                return [$fight->user1_id, $fight->user2_id];
            })
            ->unique();

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $user->balance += $user->battle_balance;
            $user->battle_balance = 0;
            $user->save();
            event(new SessionFinishedEvent($user->id, $pool));
        }
    }
}
