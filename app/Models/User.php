<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'referral_code',
        'balance',
        'battle_balance',
        'pool_id',
        'session_start_balance',
        'session_start_battle_balance',
        'session_started',
        'language',
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

    public function pools(): BelongsTo{
        return $this->belongsTo(Pool::class, 'pool_id');
    }

        // A user has one set of pre-moves
    public function preMove()
    {
        return $this->hasOne(PreMove::class);
    }

    // Referral relationships
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    // Influencer relationship
    public function influencer()
    {
        return $this->hasOne(Influencer::class);
    }

    // Generate unique referral code
    public function generateReferralCode()
    {
        do {
            $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (self::where('referral_code', $code)->exists());
        
        $this->referral_code = $code;
        $this->save();
        
        return $code;
    }

    // Get referral statistics
    public function getReferralStats()
    {
        $totalReferrals = $this->referrals()->count();
        $pendingReferrals = $this->referrals()->pending()->count();
        $validatedReferrals = $this->referrals()->validated()->count();
        
        return [
            'total' => $totalReferrals,
            'pending' => $pendingReferrals,
            'validated' => $validatedReferrals,
            'rewards_earned' => $validatedReferrals * 100 // 100 SNT per validated referral
        ];
    }
}
