<?php
namespace App\Models;

use App\Services\FightService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'pool_id',
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

    public function fHist()
    {
        return $this->hasOne(FHist::class, 'fight_id');
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
        return $this->user1->battle_balance;
    }

    public function user2Gain()
    {
        return $this->user2->battle_balance;
    }

    public function handleAutoplayFight()
    {
        return app(FightService::class)->handleAutoplayFight($this);
    }

    public function handlePoolAutoplayFight($baseBet, $poolSize)
    {
        return app(FightService::class)->handlePoolAutoplayFight($this, $baseBet, $poolSize);
    }
}
