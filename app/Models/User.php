<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Models\Challenge;
use App\Models\Fight;
use App\Models\UserSetting;
use App\Models\Pool;


class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_online',
        'autoplay_active',
        'status',
        'bet_amount',
        'wallet_address',
        'balance'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function challengesSent()
    {
        return $this->hasMany(Challenge::class, 'sender_id');
    }

    public function challengesReceived()
    {
        return $this->hasMany(Challenge::class, 'receiver_id');
    }

    public function userSetting()
    {
        return $this->hasOne(UserSetting::class);
    }

    public function fights()
    {
        return $this->hasMany(Fight::class, 'user1_id');
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'pool_user', 'user_id', 'pool_id');
    }
}
