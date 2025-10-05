<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_id',
        'milestone_reached',
        'reward_tokens',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }
}
