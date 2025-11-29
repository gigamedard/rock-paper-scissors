<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Challenge;
use App\Models\Fight;
use App\Models\UserSetting;
use App\Models\Pool;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

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
        'token_balance',
        'locked_balance',
        'balance',
        'battle_balance',
        'pool_id',
        'session_start_balance',
        'session_start_battle_balance',
        'session_started',
        'language',
        'has_received_signup_bonus',
        'is_eligible_to_refer',
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



    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }





    public function referralRewards()
    {
        return $this->hasMany(ReferralReward::class, 'referrer_id');
    }

    public function getReferralStats()
    {
        $referrals = $this->referrals; // 'referrals' est la relation hasMany sur le modèle User

        $total = $referrals->count();
        $pending = $referrals->where('status', 'pending')->count();
        $validated = $referrals->where('status', 'validated')->count();
        
        // --- CORRECTION ICI ---
        // Au lieu de multiplier, nous allons sommer les récompenses réelles
        $rewards_earned = $this->referralRewards()->sum('reward_tokens');

        return [
            'total'          => $total,
            'pending'        => $pending,
            'validated'      => $validated,
            'rewards_earned' => $rewards_earned, // Utilise la somme correcte
        ];
    }

    /** L'utilisateur est-il un influenceur ? */
    public function influencer()
    {
        return $this->hasOne(Influencer::class);
    }

    /** Les filleuls que cet utilisateur a parrainés */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /** Les frais générés par cet utilisateur (en tant que vendeur) */
    public function feesGenerated()
    {
        return $this->hasMany(InfluencerFee::class);
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

    
}
