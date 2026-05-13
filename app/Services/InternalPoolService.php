<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\User;
use App\Helpers\UserTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalPoolService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Round-robin: process ONE bet tier per call, cycling through active tiers.
     * Groups available autoplay users by their bet_amount (handles martingale-doubled bets).
     */
    public function processInternalPools(float $baseBet): array
    {
        $poolSizes = config('pool.size', [5]);
        $targetPoolSize = $poolSizes[0] ?? 5;
        $limit = 1000;

        return DB::transaction(function () use ($baseBet, $targetPoolSize, $limit) {
            $betTiers = User::where('status', 'available')
                ->where('autoplay_active', true)
                ->where('bet_amount', '>=', $baseBet)
                ->distinct()
                ->pluck('bet_amount')
                ->toArray();

            if (empty($betTiers)) {
                return [
                    'message' => "No available autoplay users with bet_amount >= {$baseBet}.",
                    'created_pools' => 0,
                    'users_processed' => 0,
                ];
            }

            sort($betTiers, SORT_NUMERIC);

            $cacheKey = 'internal_pool_bet_tier_index';
            $currentIndex = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0) % count($betTiers);

            $tierBet = $betTiers[$currentIndex];

            $nextIndex = ($currentIndex + 1) % count($betTiers);
            \Illuminate\Support\Facades\Cache::put($cacheKey, $nextIndex, now()->addMinutes(5));

            $securityCoefficient = \App\Models\GameSetting::getValue('security_coefficient', config('game_settings.security_coefficient', 1000));
            
            // [TRACE] Audit Zéro Mock - Dashboard Settings Verification
            \App\Helpers\UserTracker::info("Audit Zéro Mock: Charging tier {$tierBet}. Security Coefficient applied from DB: {$securityCoefficient}");

            $users = User::with('preMove')
                ->where('status', 'available')
                ->where('bet_amount', $tierBet)
                ->where('autoplay_active', true)
                ->where('balance', '>=', $tierBet) // On vérifie seulement s'ils peuvent payer la mise
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $scanCount = $users->count();
            if ($scanCount < $targetPoolSize) {
                $excludedQuery = User::where('status', 'available')
                    ->where('bet_amount', $tierBet)
                    ->where('autoplay_active', true)
                    ->where('balance', '<', $tierBet);

                $excludedIds = $excludedQuery->pluck('id')->toArray();
                $excludedCount = count($excludedIds);

                if ($excludedCount > 0) {
                    $baseBet = (float) collect(config('pool.base_bet', [0.01]))->min();
                    // QA Fix: Auto-stop users with insufficient funds to prevent stagnation
                    // Also reset bet_amount to base bet so they can re-enter on next session
                    User::whereIn('id', $excludedIds)->update([
                        'status'          => 'stopped',
                        'session_started' => false,
                        'bet_amount'      => $baseBet,
                    ]);
                    UserTracker::info("InternalPoolService: Auto-stopped {$excludedCount} users at tier {$tierBet} due to insufficient funds. bet_amount reset to {$baseBet}.", [
                        'tier'         => $tierBet,
                        'excluded_ids' => $excludedIds
                    ]);

                    UserTracker::warning("InternalPoolService: {$excludedCount} users at tier {$tierBet} have insufficient funds for base bet. They have been moved to 'stopped'.", ['tier' => $tierBet, 'count' => $excludedCount]);
                }

                return [
                    'message' => "Not enough users for tier {$tierBet}. Need {$targetPoolSize}, found {$scanCount}.",
                    'created_pools' => 0,
                    'users_processed' => 0,
                    'current_tier' => $tierBet,
                    'tier_index' => $currentIndex,
                ];
            }

            $chunks = $users->chunk($targetPoolSize);
            $tierCreatedPools = 0;
            $tierUsersProcessed = 0;

            foreach ($chunks as $chunk) {
                if ($chunk->count() < $targetPoolSize) {
                    continue;
                }

                $pool = Pool::create([
                    'pool_id' => Str::uuid()->toString(),
                    'base_bet' => $tierBet,
                    'pool_size' => $targetPoolSize,
                    'salt' => bin2hex(random_bytes(16)),
                    'status' => 'from_server_waitting',
                ]);

                foreach ($chunk as $user) {
                    // INITIALIZE SESSION FOR NEW ENTRANTS
                    if (!$user->session_started) {
                        $user->session_start_balance = $user->balance;
                        $user->session_start_battle_balance = 0;
                        $user->bet_amount = $tierBet; // Set Martingale baseline
                        // $user->session_started is set to true below
                    }

                    // C. Move funds for the internal battle (Martingale funding)
                    $user->balance -= $tierBet;
                    // Reset battle_balance for the new pool round to ensure integrity
                    $user->battle_balance = $tierBet;
                    $user->status = 'in_pool';
                    $user->pool_id = $pool->id;
                    $user->session_started = true;
                    $user->save();

                    $reason = 'new_session';
                    if ($user->preMove && $user->preMove->current_index > 0) {
                        $reason = 'after_defeat';
                    }
                    $this->notificationService->notifyPoolEntry($user, $pool, $reason);
                }

                $tierCreatedPools++;
                $tierUsersProcessed += $chunk->count();
            }

            if ($tierCreatedPools > 0) {
                $wallets = $users->pluck('wallet_address')->toArray();
                UserTracker::info("InternalPoolService [Round-Robin {$currentIndex}/".(count($betTiers) - 1)."]: Created {$tierCreatedPools} pools for tier {$tierBet} with {$tierUsersProcessed} users.", ['wallets' => $wallets, 'tier' => $tierBet]);
            }

            return [
                'message' => "Processed internal pools for tier {$tierBet}.",
                'base_bet' => $baseBet,
                'current_tier' => $tierBet,
                'tier_index' => $currentIndex,
                'next_tier_index' => $nextIndex,
                'active_tiers' => $betTiers,
                'created_pools' => $tierCreatedPools,
                'users_processed' => $tierUsersProcessed,
            ];
        });
    }
}
