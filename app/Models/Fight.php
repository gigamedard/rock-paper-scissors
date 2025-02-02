<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Pool;

class Fight extends Model
{
    use HasFactory;

    protected $table = 'fights';

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

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
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

    public function handlePoolAutoplayFight($baseBet, $poolSize)
    {   
        $user1Move = $this->getPreMove($this->user1_id);
        $user2Move = $this->getPreMove($this->user2_id);

        // Assign moves to user1_chosed and user2_chosed
        $this->user1_chosed = $user1Move;
        $this->user2_chosed = $user2Move;

        // Determine the result of the fight
        $result = $this->determineResult($user1Move, $user2Move);
        // Update fight result
        $this->result = $result;
        
        // Handle the case where the result is a draw
        if ($result === 'draw') {
            // Both users remain in the pool and their statuses are updated
            User::where('id', $this->user1_id)->update(['status' => 'available']);
            User::where('id', $this->user2_id)->update(['status' => 'available']);
        }
        else
        {


            // Get the winner and loser
            $winner = ($result === 'user1_win') ? $this->user1_id : $this->user2_id;
            $loser = ($result === 'user1_win') ? $this->user2_id : $this->user1_id;


            // Transfer the loser's battle balance to the winner
            $loserUser = User::find($loser);
            $winnerUser = User::find($winner);

            // Add the loser's battle balance to the winner
            $winnerUser->battle_balance += $loserUser->battle_balance;
            $winnerUser->save();

            // Reset the loser's battle balance to 0
            $loserUser->battle_balance = 0;
            $loserUser->save();

            // Remove the loser from the pool using the fight's relationship
            $this->removeUserFromPool($loser);

            // Mark the loser as available
            User::where('id', $loser)->update(['status' => 'available']);

            // Add the loser to a new pool on the server
            $this->addUserToQueue($loser,$baseBet);


        }

        
        $this->status = 'completed';
        $this->save();
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
        if (!$user1Move) {
            return 'user2_win';
        }

        if (!$user2Move) {
            return 'user1_win';
        }

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

    private function removeUserFromPool(int $userId): void
    {
        // Access the pool associated with the fight
        $pool = $this->pool;

        if ($pool) {
            // Remove the user from the pool
            User::where('id', $userId)->update(['pool_id' => null]);
        }
    }
    private function addUserToNewPool(int $userId, float $baseBet, int $poolSize): void
    {
        // Check if there is an existing pool with the same bet amount that is not full
        $pool = Pool::where('base_bet', $baseBet)
                    ->whereDoesntHave('users', function ($query) use ($poolSize) {
                        $query->havingRaw('COUNT(*) >= ?', [$poolSize]);
                    })
                    ->first();
        // If no such pool exists, create a new one
        if (!$pool) {
            $pool = Pool::create(['base_bet' => $baseBet, 'pool_size' => $poolSize]);
        }

        // Set the pool_id to the id of the created or found pool
        $pool->pool_id = $pool->id;
        $pool->save();

        // Add the user to the pool
        User::where('id', $userId)->update(['pool_id' => $pool->id]);
    }
    private function addUserToQueue(int $userId,$lastBaseBet): void
    {
        // Add the user to the queue_table
        DB::table('queue_table')->insert([
            'user_id' => $userId,
            'base_bet' => $lastBaseBet, // calculate the bbase_bet from the lastBaseBet * 2 if user have martingale activated
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
}