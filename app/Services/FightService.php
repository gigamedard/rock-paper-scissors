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

    public function __construct(HistoricalFightService $historicalFightService, Web3Helper $web3Helper)
    {
        $this->historicalFightService = $historicalFightService;
        $this->web3Helper = $web3Helper;
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
            $this->removeUserFromPool($loserId, $fight->pool);
            User::where('id', $loserId)->update(['status' => 'available']);
        }

        $this->updateBattleBalances($fight, $result);

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

    protected function updateBattleBalances(Fight $fight, $result)
    {
        $user1 = $fight->user1;
        $user2 = $fight->user2;

        if ($result === 'user1_win') {
            $user1->battle_balance += $user2->battle_balance;
            $user2->battle_balance = 0;
        } elseif ($result === 'user2_win') {
            $user2->battle_balance += $user1->battle_balance;
            $user1->battle_balance = 0;
        }

        $user1->save();
        $user2->save();
    }
}
