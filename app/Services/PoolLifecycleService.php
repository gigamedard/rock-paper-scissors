<?php

namespace App\Services;

use App\Events\PoolFinishedEvent;
use App\Events\UserBalanceUpdated;
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

        $users = User::whereIn('wallet_address', explode(',', $data['users']))->get();
        $premoveCIDs = explode(',', $data['premove_cids']);

        $this->processUsersForPool($users, $premoveCIDs, $pool->id, $pool->base_bet);

        $this->processPoolAutoMatch($pool->id);

        return ['pool_id' => $data['pool_id'], 'status' => 'processed'];
    }

    public function processPoolAutoMatch(int $poolId)
    {
        $pool = Pool::with(['users' => function ($query) {
            $query->where('status', 'in_pool')->orderBy('id');
        }])->where('pool_id', $poolId)->firstOrFail();

        $minUsers = ceil($pool->pool_size * config('pool.percentage_limit_of_pool_size'));
        if ($minUsers < 2) {
            $minUsers = 2;
        }

        while ($this->hasSufficientUsersForMatch($pool->users->count(), $minUsers)) {
            $this->executeMatchingRound($pool);
            $pool->load('users'); // Refresh the users collection
        }

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

    private function processUsersForPool($users, $premoveCIDs, $poolId, $baseBet)
    {
        foreach ($users as $index => $user) {
            if ($user->preMove->cid !== $premoveCIDs[$index]) {
                $this->handleCidMismatch($user, $baseBet, $premoveCIDs);
                continue;
            }

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

    private function handleCidMismatch($user, $baseBet, $premoveCIDs)
    {
        Log::error("CID mismatch for user: {$user->wallet_address}");
        $user->status = 'invalid';
        $user->save();
        $returned = $baseBet * config('game_settings.security_coefficient');
        $this->web3Helper->sendPayement(env('NODE_URL'), $user->wallet_address, $returned);
        Log::info("User {$user->wallet_address} has been marked as invalid and refunded {$returned} ETH.");
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
        }
    }

    private function hasSufficientUsersForMatch($userCount, $minUsers): bool
    {
        return $userCount >= $minUsers && $userCount >= 2;
    }
}
