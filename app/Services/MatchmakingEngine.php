<?php

namespace App\Services;

use App\Helpers\Web3Helper;
use App\Models\Fight;
use App\Models\Pool;
use Illuminate\Support\Facades\Log;

class MatchmakingEngine
{
    protected Web3Helper $web3Helper;
    protected SessionManager $sessionManager;

    public function __construct(Web3Helper $web3Helper, SessionManager $sessionManager)
    {
        $this->web3Helper = $web3Helper;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Process fights for a given pool ID.
     * Loops through matching rounds until insufficient users remain.
     */
    public function process(int $poolId): void
    {
        $pool = Pool::with(['users' => function ($query) {
            $query->where('status', 'in_pool')->orderBy('id');
        }])->findOrFail($poolId);

        // FIX: Prevent re-processing if the pool is already finished or being finished.
        if ($pool->status === 'from_server_finished') {
            Log::info("Pool {$poolId} already finished. Skipping matchmaking and session evaluation.");
            return;
        }

        $minUsers = ceil($pool->pool_size * config('pool.percentage_limit_of_pool_size'));
        $minUsers = max($minUsers, 2);

        $maxIterations = 20;
        $iterations = 0;

        while ($this->hasSufficientUsersForMatch($pool->users->count(), $minUsers) && $iterations < $maxIterations) {
            $this->executeMatchingRound($pool, $iterations);
            
            // Refresh users collection for the next round
            $pool->load(['users' => function ($query) {
                $query->where('status', 'in_pool')->orderBy('id');
            }]);
            
            $iterations++;
        }

        if ($iterations >= $maxIterations) {
            $wallets = $pool->users->pluck('wallet_address')->toArray();
            Log::warning("Pool {$pool->id} reached maximum iterations ({$maxIterations}). Breaking loop for users: " . implode(', ', $wallets));
        }

        $pool->update(['status' => 'from_server_finished']);

        // Delegate end of pool logic to SessionManager
        $this->sessionManager->evaluatePoolEnd($pool);
    }

    private function hasSufficientUsersForMatch(int $userCount, int $minUsers): bool
    {
        return $userCount >= $minUsers && $userCount >= 2;
    }

    private function executeMatchingRound(Pool $pool, int $iterationIndex): void
    {
        $availableUsers = $pool->users;

        // Filter out users who were eliminated in a previous matching round
        foreach ($availableUsers as $user) {
            if ($user->battle_balance < $pool->base_bet) {
                $availableUsers = $availableUsers->reject(fn ($u) => $u->id === $user->id);
            }
        }

        $dynamicSalt = hash('sha256', $pool->salt . $iterationIndex);

        $sortedAddresses = $this->web3Helper->sortAddressesWithSalt(
            $availableUsers->pluck('wallet_address')->toArray(),
            $dynamicSalt
        );

        $availableUsers = $availableUsers->sortBy(function ($user) use ($sortedAddresses) {
            return array_search($user->wallet_address, $sortedAddresses);
        })->values();

        // Remove the last user if the count is odd
        if ($availableUsers->count() % 2 !== 0) {
            $availableUsers->pop();
        }

        for ($i = 0; $i < $availableUsers->count(); $i += 2) {
            $fight = Fight::create([
                'user1_id'        => $availableUsers[$i]->id,
                'user2_id'        => $availableUsers[$i + 1]->id,
                'base_bet_amount' => $pool->base_bet,
                'status'          => 'waiting_for_result',
                'pool_id'         => $pool->id,
            ]);

            $fight->handlePoolAutoplayFight($pool->base_bet, $pool->pool_size);

            event(new \App\Events\MatchFound($availableUsers[$i], $fight->id, $availableUsers[$i + 1]->wallet_address));
            event(new \App\Events\MatchFound($availableUsers[$i + 1], $fight->id, $availableUsers[$i]->wallet_address));

            $this->notifyBattleStarted($availableUsers[$i], $availableUsers[$i + 1], $fight, $pool->id);
        }
    }

    private function notifyBattleStarted($user1, $user2, Fight $fight, int $poolId): void
    {
        foreach ([$user1, $user2] as $combatant) {
            \App\Models\GameNotification::create([
                'user_id' => $combatant->id,
                'type' => 'BATTLE_STARTED',
                'data' => [
                    'fight_id' => $fight->id,
                    'opponent_id' => ($combatant->id == $fight->user1_id) ? $fight->user2_id : $fight->user1_id,
                    'pool_id' => $poolId,
                ],
            ]);
        }
    }
}
