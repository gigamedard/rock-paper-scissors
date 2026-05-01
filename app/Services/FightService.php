<?php

namespace App\Services;

use App\Events\UserStoppedEvent;
use App\Helpers\Web3Helper;
use App\Helpers\UserTracker;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class FightService
{
    protected $historicalFightService;

    protected $web3Helper;

    protected $notificationService;

    public function __construct(HistoricalFightService $historicalFightService, Web3Helper $web3Helper, NotificationService $notificationService)
    {
        $this->historicalFightService = $historicalFightService;
        $this->web3Helper = $web3Helper;
        $this->notificationService = $notificationService;
    }

    public function handleAutoplayFight(Fight $fight)
    {
        $user1Move = $this->getPreMove($fight->user1_id);
        $user2Move = $this->getPreMove($fight->user2_id);

        $result = $this->determineResult($user1Move, $user2Move);

        $this->updateBalances($fight, $result);

        $fight->update([
            'status' => 'completed',
            'result' => $result,
        ]);

        return $result;
    }

    public function handlePoolAutoplayFight(Fight $fight, $baseBet, $poolSize)
    {
        // Real-time Event: Fight Started via NotificationService
        $this->notificationService->notifyFightStarted(User::find($fight->user1_id), User::find($fight->user2_id), $fight);
        $this->notificationService->notifyFightStarted(User::find($fight->user2_id), User::find($fight->user1_id), $fight);

        $user1Move = $this->getPreMove($fight->user1_id);
        $user2Move = $this->getPreMove($fight->user2_id);

        $fight->user1_chosed = $user1Move;
        $fight->user2_chosed = $user2Move;

        $result = $this->determineResult($user1Move, $user2Move);
        $fight->result = $result;

        $fHist = $this->historicalFightService->archiveFight($fight->id);

        if ($result === 'draw') {
            User::where('id', $fight->user1_id)->update(['status' => 'in_pool']);
            User::where('id', $fight->user2_id)->update(['status' => 'in_pool']);
        } else {
            $winnerId = ($result === 'user1_win') ? $fight->user1_id : $fight->user2_id;
            $loserId = ($result === 'user1_win') ? $fight->user2_id : $fight->user1_id;

            // Transfer the base bet amount from loser to winner in battle_balance
            $this->transferBattleBalance($winnerId, $loserId, $baseBet);

            // LOGGING ENHANCEMENT: Explicit Transfer Log
            $winnerWallet = User::find($winnerId)->wallet_address ?? 'UNKNOWN';
            $loserWallet = User::find($loserId)->wallet_address ?? 'UNKNOWN';
            UserTracker::info("[FIGHT_TRANSFER] ⚔️ Player {$winnerWallet} won against {$loserWallet}. Transferred {$baseBet} from Loser to Winner.", ['winner' => $winnerWallet, 'loser' => $loserWallet, 'amount' => $baseBet]);

            // Notify winner (User gains baseBet)
            $winnerUser = User::find($winnerId);
            if ($winnerUser) {
                $this->notificationService->notifyFightWin($winnerUser, $baseBet, $fight->id);
            }

            // Check loser's remaining battle balance
            $loserUser = User::find($loserId);
            if ($loserUser->battle_balance < $baseBet) {
                // Eliminate from pool
                $this->removeUserFromPool($loserId, $fight->pool);

                // Double the base bet for next pool
                $loserUser = $loserUser->fresh();
                $newBetAmount = $loserUser->bet_amount * 2;
                $loserUser->bet_amount = $newBetAmount;

                // Check if user has enough funds (balance + battle_balance) for the NEXT doubled bet
                $totalFunds = $loserUser->balance + $loserUser->battle_balance;

                if ($totalFunds < $newBetAmount) {
                    $loserUser->status = 'stopped';
                    $loserUser->save();
                    UserTracker::info("[FIGHT_ELIMINATION] 🛑 Player {$loserWallet} eliminated and stopped! Total Funds ({$totalFunds}) < Next Bet ({$newBetAmount}).", ['wallet' => $loserWallet, 'funds' => $totalFunds, 'next_bet' => $newBetAmount]);
                    $this->notificationService->notifyInsufficientBalance($loserUser);
                    event(new \App\Events\UserStoppedEvent($loserUser, 'insufficient_funds'));
                } else {
                    UserTracker::info("[FIGHT_ELIMINATION] ⚠️ Player {$loserWallet} eliminated from current pool, has funds ({$totalFunds}) for re-pooling. Status set to 'available' for Round Robin.", ['wallet' => $loserWallet, 'funds' => $totalFunds]);
                    $loserUser->status = 'available';
                    $loserUser->pool_id = null;
                    $loserUser->save();
                }
            } else {
                // Loser still has enough battle_balance to continue in THIS pool
                // Keep in pool for next matching round
                $loserUser->status = 'in_pool';
                $loserUser->save();
            }
        }

        $fight->status = 'completed';
        $fight->save();

        $this->historicalFightService->archiveFight($fight->id, $fHist);
    }

    public function getPreMove($userId)
    {
        $preMove = DB::table('pre_moves')->where('user_id', $userId)->first();
        if (! $preMove) {
            throw new \Exception("No pre-moves found for user ID $userId");
        }
        $moves = json_decode($preMove->moves, true);
        $currentIndex = $preMove->current_index;
        if ($currentIndex >= count($moves)) {
            $currentIndex = 0;
        }
        $nextMove = $moves[$currentIndex];
        DB::table('pre_moves')->where('user_id', $userId)->update(['current_index' => $currentIndex + 1]);

        return $nextMove;
    }

    public function determineResult($user1Move, $user2Move)
    {
        $winningCombinations = ['rock' => 'scissors', 'scissors' => 'paper', 'paper' => 'rock'];
        if (! $user1Move) {
            return 'user2_win';
        }
        if (! $user2Move) {
            return 'user1_win';
        }
        if ($user1Move === $user2Move) {
            return 'draw';
        }

        return $winningCombinations[$user1Move] === $user2Move ? 'user1_win' : 'user2_win';
    }

    protected function updateBalances(Fight $fight, $result)
    {
        $betAmount = $fight->base_bet_amount;
        if ($result === 'user1_win') {
            DB::table('users')->where('id', $fight->user1_id)->increment('balance', $betAmount);
            DB::table('users')->where('id', $fight->user2_id)->decrement('balance', $betAmount);
        } elseif ($result === 'user2_win') {
            DB::table('users')->where('id', $fight->user2_id)->increment('balance', $betAmount);
            DB::table('users')->where('id', $fight->user1_id)->decrement('balance', $betAmount);
        }
    }

    protected function transferBattleBalance($winnerId, $loserId, $amount)
    {
        $loserUser = User::find($loserId);
        $winnerUser = User::find($winnerId);

        if ($loserUser && $winnerUser) {
            $loserUser->battle_balance -= $amount;
            $winnerUser->battle_balance += $amount;

            $loserUser->save();
            $winnerUser->save();
        }
    }

    protected function removeUserFromPool(int $userId, Pool $pool): void
    {
        User::where('id', $userId)->update([
            'pool_id' => null,
            'status' => 'available',
        ]);
    }

    public function addUserToNewPool(int $userId, float $baseBet, int $poolSize): void
    {
        if (! $this->web3Helper->premoveExists($userId)) {
            return;
        }

        $user = User::find($userId);
        $currentPoolId = $user ? $user->pool_id : null;

        $pool = Pool::where('base_bet', $baseBet)
            ->where('status', 'from_server_waitting') // Only pick waiting pools
            ->where(function ($query) use ($currentPoolId) {
                if ($currentPoolId) {
                    $query->where('id', '!=', $currentPoolId);
                }
            })
            ->has('users', '<', $poolSize)
            ->orderBy('id', 'desc')
            ->first();

        if (! $pool) {
            $pool = Pool::create([
                'base_bet' => $baseBet,
                'pool_size' => $poolSize,
                'salt' => \Illuminate\Support\Str::random(10),
            ]);
            $pool->status = 'from_server_waitting';
            $pool->save();
            $walletAddress = $user ? $user->wallet_address : 'UNKNOWN';
            \Illuminate\Support\Facades\Log::info("FightService: Created NEW pool {$pool->id} for user {$walletAddress}");
        } else {
            $walletAddress = $user ? $user->wallet_address : 'UNKNOWN';
            \Illuminate\Support\Facades\Log::info("FightService: Found EXISTING waiting pool {$pool->id} for user {$walletAddress}");
        }

        $pool->pool_id = $pool->id;
        $pool->status = 'from_server_waitting';
        $pool->save();

        User::where('id', $userId)->update([
            'pool_id' => $pool->id,
            'status' => 'in_pool',
        ]);

        $walletAddress = $user ? $user->wallet_address : 'UNKNOWN';
        \Illuminate\Support\Facades\Log::info("FightService: Assigned user {$walletAddress} to pool {$pool->id}. DB Update executed.");

        // Notify Pool Entry
        $user = User::find($userId);
        if ($user) {
            // Logic to determine reason could be passed in, but contextually this method is called for continuing users (winners)
            $this->notificationService->notifyPoolEntry($user, $pool, 'pool_winner');
        }
    }
}
