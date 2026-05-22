<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_id',
        'status',
        'remaining_sessions',
        'expires_at',
        'purchased_at',
        'tx_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'purchased_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
