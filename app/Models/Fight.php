<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Fight extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fights';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user1_id',
        'user2_id',
        'status',
        'result',
        'user1_chosed',
        'user2_chosed',
        'base_bet_amount',
        'max_bet_amount',
    ];

    /**
     * Get the user1 that owns the fight.
     */
    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Get the user2 that owns the fight.
     */
    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }


    public function user1Verdict()
    {
        return match ($this->result) {
            'user1_win' => 'win',
            'user2_win' => 'lose',
            default => 'draw',
        };
    }
    

    public function user2Verdict()
    {
        return match ($this->result) {
            'user2_win' => 'win',
            'user1_win' => 'lose',
            default => 'draw',
        };
    }



    public function user1Gain()
    {
        return match ($this->result) {
            'user1_win' => $this->base_bet_amount,
            'user2_win' => -$this->base_bet_amount,
            default => 0,
        };
    }


    public function user2Gain()
    {
        return match ($this->result) {
            'user2_win' => $this->base_bet_amount,
            'user1_win' => -$this->base_bet_amount,
            default => 0,
        };
    }

    public function handleAutoplayFight()
    {
        // Retrieve pre-moves for both players
        $user1Move = $this->getPreMove($this->user1_id);
        $user2Move = $this->getPreMove($this->user2_id);
    
        // Determine the result
        $result = $this->determineResult($user1Move, $user2Move);
    
        // Update balances based on the result
        $this->updateBalances($result);
    
        // Update the fight status and result
        $this->update([
            'status' => 'completed',
            'result' => $result,
        ]);
    
        return $result;
    }
    
    private function getPreMove($userId)
    {
        // Retrieve pre-moves for the user
        $preMove = DB::table('pre_moves')->where('user_id', $userId)->first();

        if (!$preMove) {
            throw new \Exception("No pre-moves found for user ID $userId");
        }

        $moves = json_decode($preMove->moves, true);
        $currentIndex = $preMove->current_index;

        // Reset index if all moves are used
        if ($currentIndex >= count($moves)) {
            $currentIndex = 0;
        }

        // Get the next move
        $nextMove = $moves[$currentIndex];

        // Increment the current index and save back
        DB::table('pre_moves')->where('user_id', $userId)->update([
            'current_index' => $currentIndex + 1,
        ]);

        return $nextMove;
    }

    private function determineResult($user1Move, $user2Move)
    {
        // Define the winning logic
        $winningCombinations = [
            'rock' => 'scissors',
            'scissors' => 'paper',
            'paper' => 'rock',
        ];

        if ($user1Move === $user2Move) {
            return 'draw';
        }

        return $winningCombinations[$user1Move] === $user2Move ? 'user1_win' : 'user2_win';
    }

    private function updateBalances($result)
    {
        $betAmount = $this->base_bet_amount;

        if ($result === 'user1_win') {
            DB::table('users')->where('id', $this->user1_id)->increment('balance', $betAmount);
            DB::table('users')->where('id', $this->user2_id)->decrement('balance', $betAmount);
        } elseif ($result === 'user2_win') {
            DB::table('users')->where('id', $this->user2_id)->increment('balance', $betAmount);
            DB::table('users')->where('id', $this->user1_id)->decrement('balance', $betAmount);
        }
    }

    
}
