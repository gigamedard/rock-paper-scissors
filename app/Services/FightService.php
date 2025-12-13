<?php

namespace App\Services;

use App\Models\Fight;
use App\Models\User;
use App\Models\Pool;
use Illuminate\Support\Facades\DB;
use App\Helpers\Web3Helper;
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

            $this->transferBalances($winnerId, $loserId);
            
            // Notify winner
            $winnerUser = User::find($winnerId);
            if ($winnerUser) {
                // The gain is the loser's battle_balance. Detailed calc might be needed if exact gain is required.
                // transferBalances moves entire battle_balance. 
                // We'll approximate or just notify 'win'. The requirement says "gain".
                // In transferBalances: $winnerUser->battle_balance += $loserUser->battle_balance;
                // We should calculate that amount.
                $loserUser = User::find($loserId); // transferBalances re-fetches, but we need amount here.
                // Actually transferBalances fetches it. I should maybe modify transferBalances or fetch here.
                // Fetching here is safer for now.
                $gain = $loserUser ? $loserUser->battle_balance : 0; 
                $this->notificationService->notifyFightWin($winnerUser, $gain, $fight->id);
            }

            $this->removeUserFromPool($loserId, $fight->pool);
            User::where('id', $loserId)->update(['status' => 'available']);
        }



        $fight->status = 'completed';
        $fight->save();

        $this->historicalFightService->archiveFight($fight->id, $fHist);
    }

    public function getPreMove($userId)
    {
        $preMove = DB::table('pre_moves')->where('user_id', $userId)->first();
        if (!$preMove) {
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
        if (!$user1Move) return 'user2_win';
        if (!$user2Move) return 'user1_win';
        if ($user1Move === $user2Move) return 'draw';
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

    protected function transferBalances($winnerId, $loserId)
    {
        $loserUser = User::find($loserId);
        $winnerUser = User::find($winnerId);

        $winnerUser->battle_balance += $loserUser->battle_balance;
        $winnerUser->save();

        $loserUser->battle_balance = 0;
        $loserUser->save();
    }

    protected function removeUserFromPool(int $userId, Pool $pool): void
    {
        User::where('id', $userId)->update(['pool_id' => null]);
    }

    public function addUserToNewPool(int $userId, float $baseBet, int $poolSize): void
    {
        if (!$this->web3Helper->premoveExists($userId)) {
            return;
        }

        $pool = Pool::where('base_bet', $baseBet)
            ->where('status', 'from_server_waitting') // Only pick waiting pools
            ->has('users', '<', $poolSize)
            ->orderBy('id', 'desc')
            ->first();

        if (!$pool) {
            $pool = Pool::create([
                'base_bet' => $baseBet, 
                'pool_size' => $poolSize,
                'salt' => \Illuminate\Support\Str::random(10),
            ]);
            $pool->status = 'from_server_waitting';
            $pool->save();
        }

        $pool->pool_id = $pool->id;
        $pool->status = 'from_server_waitting';
        $pool->save();

        User::where('id', $userId)->update([
            'pool_id' => $pool->id,
            'status' => 'in_pool'
        ]);
        
        // Notify Pool Entry
        $user = User::find($userId);
        if ($user) {
            // Logic to determine reason could be passed in, but contextually this method is called for continuing users (winners)
            $this->notificationService->notifyPoolEntry($user, $pool, 'pool_winner');
        }
    }


}
