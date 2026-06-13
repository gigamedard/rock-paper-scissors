<?php

namespace App\Services;

use App\Events\UserBalanceUpdated;
use App\Helpers\Web3Helper;
use App\Helpers\UserTracker;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PoolReconstructionService
{
    protected Web3Helper $web3Helper;

    public function __construct(Web3Helper $web3Helper)
    {
        $this->web3Helper = $web3Helper;
    }

    /**
     * Handles the PoolEmited event from the blockchain.
     * Validates users, ejects intruders, and reconstructs the pool in the database.
     * Pool organization by bet_amount tier is handled upstream by the batch processor
     * (InternalPoolService / AutoMatchService) — not here.
     *
     * @param array $data Raw event data from the Node.js bridge
     * @return array Status of the reconstruction
     */
    public function reconstruct(array $data): array
    {
        $this->validatePayload($data);

        $poolSalt = $data['pool_salt'];

        if (Pool::where('salt', $poolSalt)->exists()) {
            Log::info("Pool with salt {$poolSalt} already processed. Skipping.");
            return ['status' => 'already_processed'];
        }

        $blockchainUsers    = is_array($data['users'])        ? $data['users']        : explode(',', $data['users']);
        $premoveCIDs        = is_array($data['premove_cids']) ? $data['premove_cids'] : explode(',', $data['premove_cids']);
        $baseBetEther       = $this->web3Helper->weiToEther($data['base_bet']);
        $blockchainBalances = $data['balances'] ?? [];

        // 1. Separate valid users from intruders
        $validationResult = $this->validateUsers($blockchainUsers, $premoveCIDs);
        $validUsers       = $validationResult['valid'];
        $invalidAddresses = $validationResult['invalid'];

        // 2. Handle intruders (if any)
        if (!empty($invalidAddresses)) {
            $this->ejectIntruders($invalidAddresses, $baseBetEther);
            return ['status' => 'invalidated_intruders', 'ejected' => $invalidAddresses];
        }

        // 3. Pool is valid — confirm on blockchain
        $this->validatePoolOnBlockchain($validUsers, $baseBetEther);

        // 4. Register pool in DB
        $pool = $this->createPool($data['pool_id'], $baseBetEther, $poolSalt, count($blockchainUsers));

        // 5. Initialize users for this pool
        $this->initializeUsersForPool($validUsers, $pool, $baseBetEther, $blockchainBalances);

        $pool->status = 'from_server_waitting';
        $pool->save();

        return ['pool_id' => $pool->id, 'status' => 'processed'];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validatePayload(array $data): void
    {
        if (
            empty($data['pool_id'])      ||
            empty($data['base_bet'])     ||
            empty($data['users'])        ||
            empty($data['premove_cids']) ||
            empty($data['pool_salt'])
        ) {
            throw new \InvalidArgumentException('Missing required parameters for PoolEmited event.');
        }
    }

    private function validateUsers(array $blockchainUsers, array $premoveCIDs): array
    {
        $validUsers       = [];
        $invalidAddresses = [];

        foreach ($blockchainUsers as $index => $walletAddress) {
            $expectedCid = $premoveCIDs[$index] ?? null;
            $user        = User::where('wallet_address', strtolower($walletAddress))->first();

            if (!$user || !$user->preMove || $user->preMove->cid !== $expectedCid) {
                $foundCid = ($user && $user->preMove) ? $user->preMove->cid : 'None/Not Found';
                Log::error("Validation failed for user: {$walletAddress}. Expected: {$expectedCid}, Found: {$foundCid}");

                if ($user) {
                    $user->status = 'invalid';
                    $user->save();
                }
                $invalidAddresses[] = $walletAddress;
            } else {
                $validUsers[] = $user;
            }
        }

        return ['valid' => $validUsers, 'invalid' => $invalidAddresses];
    }

    private function ejectIntruders(array $invalidAddresses, float $baseBetEther): void
    {
        Log::warning('Intruders detected. Refunding and invalidating: ' . implode(', ', $invalidAddresses));
        \App\Jobs\ReconstructPoolJob::dispatch($invalidAddresses, $baseBetEther);
    }

    private function validatePoolOnBlockchain(array $validUsers, float $baseBetEther): void
    {
        $validWallets = array_map(fn($u) => $u->wallet_address, $validUsers);
        Log::info('Pool 100% valid. Triggering smart contract validation for: ' . implode(', ', $validWallets));
        \App\Jobs\ReconstructPoolJob::dispatch([], $baseBetEther);
    }

    private function createPool(string $poolId, float $baseBetEther, string $salt, int $poolSize): Pool
    {
        return Pool::create([
            'pool_id'   => $poolId,
            'base_bet'  => $baseBetEther,
            'salt'      => $salt,
            'pool_size' => $poolSize,
        ]);
    }

    /**
     * Assign users to their pool and initialize their battle funds.
     *
     * Rules (project_context.md §2A) :
     *  - battle_balance = pool base_bet  (funds dedicated to this pool's fights)
     *  - bet_amount     = pool base_bet  on session start (Martingale baseline)
     *                     kept as-is    for continuing sessions (already doubled by SessionManager)
     *
     * Blockchain balance sync is performed ONLY for players starting a NEW session.
     * Players already in an active session keep their off-chain DB balance (Bug #1 fix).
     * The batch processor (InternalPoolService) guarantees that continuing players arrive
     * in a pool whose base_bet matches their current bet_amount — no re-grouping needed here.
     */
    private function initializeUsersForPool(array $users, Pool $pool, float $baseBetEther, array $blockchainBalances): void
    {
        $securityCoefficient = \App\Models\GameSetting::getValue(
            'security_coefficient',
            config('game_settings.security_coefficient', 1000)
        );
        $requiredCapital = $baseBetEther * $securityCoefficient;
        
        // [TRACE] Audit Zéro Mock - Dashboard Settings Verification
        \App\Helpers\UserTracker::info("Audit Zéro Mock: Pool #{$pool->id} Initialization. Security Coefficient from DB: {$securityCoefficient} | Required Capital: {$requiredCapital} ETH");

        foreach ($users as $index => $user) {
            // A. Sync balance from blockchain — only for brand-new sessions.
            //    A player mid-session retains their off-chain balance (Bug #1 fix).
            if (!$user->session_started && isset($blockchainBalances[$index])) {
                $onChainBalance = Web3Helper::weiToEther($blockchainBalances[$index]);

                if ($onChainBalance <= 0 && $user->balance > 0) {
                    Log::warning("Race condition fallback: on-chain balance is 0, keeping local DB balance ({$user->balance} ETH) for {$user->wallet_address}.");
                } else {
                    $user->balance = $onChainBalance;
                }
            }

            // B. Safety check (Security Coefficient)
            $requiredBalance = !$user->session_started ? $requiredCapital : $baseBetEther;
            if ($user->balance < $requiredBalance) {
                UserTracker::error(
                    "User {$user->wallet_address} rejected from Pool {$pool->id}: Insufficient " . 
                    (!$user->session_started ? "security margin" : "balance") . ".", 
                    ['wallet' => $user->wallet_address]
                );
                $user->status  = 'stopped';
                $user->pool_id = null;
                $user->save();
                continue;
            }


            // C. Session initialization — first pool of a new session only
            if (!$user->session_started) {
                $user->session_start_balance        = $user->balance;
                $user->session_start_battle_balance = 0;
                $user->session_started              = true;

                // Initialize bet_amount to the pool's base_bet (Martingale baseline).
                // For continuing sessions, SessionManager already set the correct doubled value.
                $user->bet_amount = $baseBetEther;

                $user->preMove->session_first_pool_id = $pool->id;
                $user->preMove->save();

                event(new \App\Events\SessionStarted($user, $user->wallet_address, (float)$user->balance));
            }

            // D. Move funds: balance → battle_balance = pool base_bet
            //    The batch processor guarantees this pool's base_bet == user's bet_amount.
            
            // [TRACE] Audit Zéro Mock - Upfront Fees (Handled On-Chain, recorded here for tracking)
            $feePercent = (float) \App\Models\GameSetting::getValue('smart_contract_fee_percentage');
            $requiredStake = $baseBetEther * $securityCoefficient;
            $feeAmount = $requiredStake * ($feePercent / 100);

            // Note: We DO NOT deduct feeAmount from $user->balance here anymore, 
            // because it is already deducted on-chain during submitPremoveCID.
            // The Laravel balance is synced from the on-chain balance which is already net of fees.

            // Track the fee in the DB (for dashboard stats)
            \App\Models\InfluencerFee::create([
                'user_id' => $user->id,
                'fee_amount_avax' => $feeAmount,
                'language_code' => 'system'
            ]);

            UserTracker::info(
                "Audit Zéro Mock: Upfront Fee applied: {$feeAmount} ETH ({$feePercent}%) for Player {$user->wallet_address} joining Pool #{$pool->id}",
                ['wallet' => $user->wallet_address]
            );

            $user->balance       -= $baseBetEther;
            $user->battle_balance = $baseBetEther;

            $user->status  = 'in_pool';
            $user->pool_id = $pool->id;
            $user->save();

            UserTracker::info(
                "[POOL_INIT] Player {$user->wallet_address} → pool #{$pool->id} | base_bet={$baseBetEther} | bet_amount={$user->bet_amount} | battle_balance={$user->battle_balance}",
                ['wallet' => $user->wallet_address]
            );

            event(new UserBalanceUpdated($user));
        }
    }
}
