<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    
    
}
