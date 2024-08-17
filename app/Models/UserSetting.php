<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'base_bet_amount', 'max_bet_amount', 'same_bet_match'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

