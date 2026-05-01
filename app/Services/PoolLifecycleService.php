<?php

namespace App\Services;

use App\Events\PoolFinishedEvent;
use App\Events\SessionFinishedEvent;
use App\Events\UserBalanceUpdated;
use App\Helpers\Web3Helper;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use App\Helpers\UserTracker;
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

        $blockchainUsers = is_array($data['users']) ? $data['users'] : explode(',', $data['users']);
        $premoveCIDs = is_array($data['premove_cids']) ? $data['premove_cids'] : explode(',', $data['premove_cids']);
        $baseBetEther = $this->web3Helper->weiToEther($data['base_bet']);

        // Prevent duplicate processing
        if (\App\Models\Pool::where('salt', $data['pool_salt'])->exists()) {
            Log::info("Pool with salt {$data['pool_salt']} already processed. Skipping.");
            return ['status' => 'already_processed'];
        }

        // 1. Sort users into Valid vs Intruders
        $validUsers = [];
        $invalidAddresses = [];

        foreach ($blockchainUsers as $index => $walletAddress) {
            Log::info("Validating user {$index}: {$walletAddress}");
            $expectedCid = $premoveCIDs[$index] ?? null;
            $user = User::where('wallet_address', $walletAddress)->first();

            if (! $user || ! $user->preMove || $user->preMove->cid !== $expectedCid) {
                $foundCid = ($user && $user->preMove) ? $user->preMove->cid : 'None/Not Found in DB';
                Log::error("Validation failed for user: {$walletAddress}. Expected CID: {$expectedCid}, Found in DB: {$foundCid}");
                if ($user) {
                    $user->status = 'invalid';
                    $user->save();
                }
                $invalidAddresses[] = $walletAddress;
            } else {
                $validUsers[] = $user;
            }
        }

        // 2. Branch: If any intruder exists, invalidate the pool on the blockchain so it can unlock and replace them.
        if (! empty($invalidAddresses)) {
            Log::warning('Intruders detected in emitted pool. Refunding and invalidating users: '.implode(', ', $invalidAddresses));

            // Refund the exact balances collected for intruders
            Web3Helper::refundUsers(env('NODE_URL'), $invalidAddresses);

            // Tell the smart contract to eject them and unlock the pool for new participants
            Web3Helper::invalidatePoolUsers(env('NODE_URL'), $baseBetEther, $invalidAddresses);

            return ['status' => 'invalidated_intruders', 'ejected' => $invalidAddresses];
        }

        // 3. Pool is 100% Valid. Tell the smart contract to finalize it and clear the users.
        $validWallets = array_map(fn($u) => $u->wallet_address, $validUsers);
        Log::info('Pool is 100% valid. Triggering smart contract validation for users: '.implode(', ', $validWallets));
        try {
            Web3Helper::validatePool(env('NODE_URL'), $baseBetEther);
        } catch (\Exception $e) {
            Log::warning('validatePool call failed (non-blocking): '.$e->getMessage());
        }

        // Only now do we register the pool in the Database
        $pool = $this->createPoolFromEvent($data);
        $blockchainBalances = $data['balances'] ?? [];
        $securityCoefficient = \App\Models\GameSetting::getValue('security_coefficient', config('game_settings.security_coefficient', 1000));

        foreach ($validUsers as $index => $user) {
            // A. Sync balance from Blockchain Truth
            if (isset($blockchainBalances[$index])) {
                $onChainBalance = Web3Helper::weiToEther($blockchainBalances[$index]);
                
                // Fallback: If blockchain says 0 but DB says > 0, trust DB temporarily 
                // to avoid race condition with the DepositReceived event listener.
                if ($onChainBalance <= 0 && $user->balance > 0) {
                     Log::warning("Race condition detected for {$user->wallet_address}: On-chain balance is 0, using local DB balance ({$user->balance} ETH) instead.");
                } else {
                     $user->balance = $onChainBalance;
                }
            }

            // B. Safety Check: Verify security margin (Coefficient rule)
            $requiredCapital = $baseBetEther * $securityCoefficient;
            if ($user->balance < $requiredCapital) {
                 UserTracker::error("User {$user->wallet_address} rejected from Pool {$pool->id}: Insufficient security margin ({$user->balance} < {$requiredCapital})", ['wallet' => $user->wallet_address]);
                 $user->status = 'stopped'; // Mark as stopped if they can't afford the margin
                 $user->pool_id = null;
                 $user->save();
                 continue;
            }

            // C. Move funds for the battle
            if (!$user->session_started) {
                $user->session_start_balance = $user->balance;
                $user->session_start_battle_balance = 0;
                $user->session_started = true;

                $user->preMove->session_first_pool_id = $pool->id;
                $user->preMove->save();
            }

            $user->balance -= $baseBetEther;
            $user->battle_balance = $baseBetEther;
            
            $user->status = 'in_pool';
            $user->pool_id = $pool->id;
            $user->save();

            event(new UserBalanceUpdated($user));
        }

        $pool->status = 'from_server_waitting';
        $pool->save();

        // Trigger fight processing immediately
        try {
            $validWallets = array_map(function ($u) {
                return $u->wallet_address;
            }, $validUsers);
            Log::info("Starting processPoolAutoMatch for pool {$pool->id} with users: ".implode(', ', $validWallets));
            $this->processPoolAutoMatch($pool->id);
        } catch (\Exception $e) {
            Log::error("processPoolAutoMatch failed for pool {$pool->id}: ".$e->getMessage());
        }

        return ['pool_id' => $data['pool_id'], 'status' => 'processed'];
    }

    /**
     * Process fights for a pool. Restored from commit 9c64dc1.
     * Loops through matching rounds until insufficient users remain.
     */
    public function processPoolAutoMatch(int $poolId)
    {
        $pool = Pool::with(['users' => function ($query) {
            $query->where('status', 'in_pool')->orderBy('id');
        }])->findOrFail($poolId);

        $minUsers = ceil($pool->pool_size * config('pool.percentage_limit_of_pool_size'));
        if ($minUsers < 2) {
            $minUsers = 2;
        }

        $maxIterations = 20; // Reduced from 100 for stability and to prevent infinite loops
        $iterations = 0;

        while ($this->hasSufficientUsersForMatch($pool->users->count(), $minUsers) && $iterations < $maxIterations) {
            $this->executeMatchingRound($pool, $iterations);
            $pool->load(['users' => function ($query) {
                $query->where('status', 'in_pool')->orderBy('id');
            }]); // Refresh the users collection
            $iterations++;
        }

        if ($iterations >= $maxIterations) {
            $wallets = $pool->users->pluck('wallet_address')->toArray();
            Log::warning("Pool {$pool->id} reached maximum iterations ({$maxIterations}). Breaking loop for users: ".implode(', ', $wallets));
        }

        $this->finishPool($pool);
        $pool->update(['status' => 'from_server_finished']);

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

    private function executeMatchingRound(Pool $pool, int $iterationIndex = 0)
    {
        $availableUsers = $pool->users;

        // Note: Debiting (balance -> battle_balance) is now handled ONCE in handlePoolEmitedEvent.
        // This loop only filters out users who might have been eliminated in a previous matching round.
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
            foreach ([$availableUsers[$i], $availableUsers[$i + 1]] as $combatant) {
                \App\Models\GameNotification::create([
                    'user_id' => $combatant->id,
                    'type' => 'BATTLE_STARTED',
                    'data' => [
                        'fight_id' => $fight->id,
                        'opponent_id' => ($combatant->id == $fight->user1_id) ? $fight->user2_id : $fight->user1_id,
                        'pool_id' => $pool->id,
                    ],
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
        // Retrieve all users who actually fought in this pool
        $fightUserIds = Fight::where('pool_id', $pool->id)
            ->get()
            ->flatMap(function ($fight) {
                return [$fight->user1_id, $fight->user2_id];
            })
            ->unique()
            ->values();

        $foughtUsers = User::whereIn('id', $fightUserIds)->get();

        // Also retrieve users who were debited (battle_balance > 0) but never fought
        // This happens when a pool ends with an odd number or insufficient users after round exits
        $allPoolUserIds = $pool->users()
            ->where('battle_balance', '>', 0)
            ->pluck('id')
            ->diff($fightUserIds);

        $noFightUsers = User::whereIn('id', $allPoolUserIds)->get();

        // --- Process users who fought: credit battle_balance gains ---
        foreach ($foughtUsers as $user) {
            $gained = $user->battle_balance;
            $user->balance += $user->battle_balance;
            $totalNewBalance = $user->balance;
            $user->battle_balance = 0;
            $user->status = 'available';
            $user->pool_id = null;
            $user->save();

            UserTracker::info("[POOL_FINISH] 💰 Session ended for Player {$user->wallet_address}. Gained from battles: {$gained}. Total Internal Balance is now: {$totalNewBalance}. Player status reset to 'available' for next pools.", ['wallet' => $user->wallet_address, 'gained' => $gained, 'new_balance' => $totalNewBalance]);

            event(new SessionFinishedEvent($user->id, $pool));
        }

        // --- Process users who were debited but never fought: REFUND their bet ---
        foreach ($noFightUsers as $user) {
            $refunded = $user->battle_balance;
            $user->balance += $refunded; // Refund the deducted bet
            $user->battle_balance = 0;
            // Reset session tracking so q-value calculation won't give false 0
            $user->session_started = false;
            $user->session_start_balance = 0;
            $user->session_start_battle_balance = 0;
            $user->status = 'available';
            $user->pool_id = null;
            $user->save();

            UserTracker::info("[POOL_FINISH] 🔄 Player {$user->wallet_address} had no fight in this pool. Refunded {$refunded} ETH bet. Balance restored to: {$user->balance}.", ['wallet' => $user->wallet_address, 'refunded' => $refunded, 'balance' => $user->balance]);
        }
    }
}
