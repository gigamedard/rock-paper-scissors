<?php

namespace App\Services;

use App\Helpers\Web3Helper;
use App\Helpers\UserTracker;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

            $user1 = User::find($fight->user1_id);
            $user2 = User::find($fight->user2_id);
            if ($user1) event(new \App\Events\FightResult($user1, 'draw', '0', (string)$user1->balance, $user1Move, $user2Move));
            if ($user2) event(new \App\Events\FightResult($user2, 'draw', '0', (string)$user2->balance, $user2Move, $user1Move));
        } else {
            $winnerId = ($result === 'user1_win') ? $fight->user1_id : $fight->user2_id;
            $loserId = ($result === 'user1_win') ? $fight->user2_id : $fight->user1_id;

            // Transfer the base bet amount from loser to winner in battle_balance
            $this->transferBattleBalance($winnerId, $loserId, $baseBet);

            // LOGGING ENHANCEMENT: Explicit Transfer Log
            $winnerUser = User::find($winnerId);
            $loserUser = User::find($loserId);
            $winnerWallet = $winnerUser->wallet_address ?? 'UNKNOWN';
            $loserWallet = $loserUser->wallet_address ?? 'UNKNOWN';
            UserTracker::info("[FIGHT_TRANSFER] ⚔️ Player {$winnerWallet} won against {$loserWallet}. Transferred {$baseBet} from Loser to Winner.", ['winner' => $winnerWallet, 'loser' => $loserWallet, 'amount' => $baseBet]);

            if ($winnerUser) event(new \App\Events\FightResult($winnerUser, 'win', "+{$baseBet}", (string)$winnerUser->balance, ($result === 'user1_win' ? $user1Move : $user2Move), ($result === 'user1_win' ? $user2Move : $user1Move)));
            if ($loserUser) event(new \App\Events\FightResult($loserUser, 'loss', "-{$baseBet}", (string)$loserUser->balance, ($result === 'user1_win' ? $user2Move : $user1Move), ($result === 'user1_win' ? $user1Move : $user2Move)));

            // Notify winner (User gains baseBet)
            if ($winnerUser) {
                $this->notificationService->notifyFightWin($winnerUser, $baseBet, $fight->id);
            }

            // Check loser's remaining battle balance
            if ($loserUser->battle_balance < $baseBet) {
                UserTracker::info("[FIGHT_ELIMINATION] ⚠️ Player {$loserWallet} eliminated from current pool matching (battle_balance < baseBet). Will wait for SessionManager to evaluate session.", ['wallet' => $loserWallet, 'battle_balance' => $loserUser->battle_balance]);
            }

            // Add loser to queue_table (legacy compatibility)
            try {
                DB::table('queue_table')->insert([
                    'user_id' => $loserId,
                    'created_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error("Queue table insert failed: " . $e->getMessage());
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
        // PreMove model casts 'moves' to array, so it is already an array if retrieved via Eloquent.
        // But since we use DB::table(), it returns a JSON string, which json_decode makes an array.
        // Wait, if it is stored as JSON in DB and cast is array, maybe sometimes it's double-encoded?
        // Let's decode it safely.
        $moves = is_string($preMove->moves) ? json_decode($preMove->moves, true) : $preMove->moves;
        if (is_string($moves)) {
            $moves = json_decode($moves, true);
        }
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
}
