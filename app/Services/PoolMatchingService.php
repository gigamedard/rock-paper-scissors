<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\Fight;
use App\Helpers\Web3Helper;
use Illuminate\Support\Facades\Log;

class PoolMatchingService
{
    protected $web3Helper;

    public function __construct(Web3Helper $web3Helper)
    {
        $this->web3Helper = $web3Helper;
    }

    public function match(Pool $pool): void
    {
        $availableUsers = $this->getAvailableUsersForMatch($pool);

        $sortedWallets = $this->web3Helper->sortAddressesWithSalt(
            $availableUsers->pluck('wallet_address')->toArray(),
            $pool->salt
        );

        $sortedUsers = $availableUsers->sortBy(function ($user) use ($sortedWallets) {
            return array_search($user->wallet_address, $sortedWallets);
        })->values();

        if ($sortedUsers->count() % 2 !== 0) {
            $sortedUsers->pop();
        }

        $this->createFightsForMatchedUsers($pool, $sortedUsers);
    }

    private function getAvailableUsersForMatch(Pool $pool)
    {
        return $pool->users->filter(function ($user) use ($pool) {
            if ($user->balance >= $pool->base_bet) {
                $user->balance -= $pool->base_bet;
                $user->battle_balance += $pool->base_bet;
                $user->save();
                return true;
            }
            return false;
        })->values();
    }

    private function createFightsForMatchedUsers(Pool $pool, $users)
    {
        for ($i = 0; $i < $users->count(); $i += 2) {
            $fight = Fight::create([
                'pool_id' => $pool->id,
                'user1_id' => $users[$i]->id,
                'user2_id' => $users[$i + 1]->id,
                'base_bet_amount' => $pool->base_bet,
                'status' => 'waiting_for_result',
            ]);

            $fight->handlePoolAutoplayFight($pool->base_bet, $pool->pool_size);
        }
    }
}
