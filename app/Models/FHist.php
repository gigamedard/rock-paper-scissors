<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FHist extends Model
{
    use HasFactory;

    protected $fillable = [
        'pool_id',
        'fight_id',
        'user1_id',
        'user1_address',
        'user1_balance',
        'old_user1_balance',
        'user1_battle_balance',
        'user1_premove_index',
        'user1_move',
        'user1_gain',
        'user2_id',
        'user2_address',
        'user2_balance',
        'old_user2_balance',
        'user2_battle_balance',
        'user2_premove_index',
        'user2_move',
        'user2_gain',
    ];

    public function pool()
    {
        return $this->belongsTo(Pool::class);
    }

    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }
}
