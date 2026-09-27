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
        'initial_base_bet',
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
        'target_q',
        'cooldown_time',
        'payout_signature',
        'payout_deadline',
        'last_synced_limits_hash',
    ];

    protected $appends = [
        'active_limits',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'target_q' => 'float',
        'cooldown_time' => 'integer',
        'cooldown_until' => 'datetime',
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

    public function userCards()
    {
        return $this->hasMany(UserCard::class);
    }

    /**
     * Cooldown de base (minutes) fixé par le PALIER de mise de base.
     * Le joueur subit exactement cette durée ; seules les cartes la réduisent.
     * Le palier de référence est `initial_base_bet` (fallback : bet_amount).
     */
    public function getBaseCooldownMinutes(): float
    {
        $cooldowns = config('economy.cooldowns', []);
        $tier = $this->initial_base_bet ?? $this->bet_amount;

        if ($tier !== null) {
            if (isset($cooldowns[(string) $tier])) {
                return (float) $cooldowns[(string) $tier];
            }
            foreach ($cooldowns as $key => $minutes) {
                if (abs((float) $key - (float) $tier) < 1e-9) {
                    return (float) $minutes;
                }
            }
        }

        return (float) config('economy.cooldown_default', 3 * 24 * 60);
    }

    /**
     * Le joueur a-t-il un déblocage "cas spécial" du plafond Q (au-delà du hard cap) ?
     * Représenté par une carte active `q_special_unlock` (récompense dédiée).
     */
    public function hasSpecialQUnlock(): bool
    {
        return $this->userCards()
            ->where('status', 'available')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('card', fn ($q) => $q->where('effect_type', 'q_special_unlock'))
            ->exists();
    }

    public function getActiveLimits()
    {
        $maxBaseBet = 100.0;

        // --- Q ceiling : par défaut 1.2, débloqué à 2.0 avec >= N filleuls validés ---
        $referralsRequired = (int) \App\Models\GameSetting::getValue(
            'referrals_required_for_max',
            config('economy.q.referrals_required_for_max', 2)
        );
        $validatedReferrals = $this->referrals()->where('status', 'validated')->count();

        $maxQ = $validatedReferrals >= $referralsRequired
            ? (float) \App\Models\GameSetting::getValue('max_q_referral', config('economy.q.max_q_referral', 2.0))
            : (float) \App\Models\GameSetting::getValue('max_q_base', config('economy.q.max_q_base', 1.2));

        // Hard cap des cartes (3.0), sauf déblocage spécial (jusqu'à 4.0).
        $qHardCap = (float) \App\Models\GameSetting::getValue('max_q_hard', config('economy.q.max_q_hard', 3.0));
        if ($this->hasSpecialQUnlock()) {
            $qHardCap = (float) \App\Models\GameSetting::getValue('max_q_special', config('economy.q.max_q_special', 4.0));
        }

        // --- Cooldown FIXE par palier de mise ---
        $minCooldown = $this->getBaseCooldownMinutes();

        $activeCards = $this->userCards()
            ->with('card')
            ->where('status', 'available')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->sortBy(function ($userCard) {
                // Apply percentage reductions first (effect_value < 1), then fixed values
                return $userCard->card && $userCard->card->effect_value < 1 ? 0 : 1;
            });

        foreach ($activeCards as $userCard) {
            $card = $userCard->card;
            if (!$card || !$card->is_active) {
                continue;
            }
            if ($card->effect_type === 'base_bet_modifier') {
                $maxBaseBet += (float)$card->effect_value;
            } elseif ($card->effect_type === 'ceiling_increase') {
                $maxQ += (float)$card->effect_value;
            } elseif ($card->effect_type === 'cooldown_reduction') {
                $effectValue = (float)$card->effect_value;
                if ($effectValue < 1) {
                    $minCooldown = $minCooldown * (1 - $effectValue);
                } else {
                    $minCooldown = max(0, $minCooldown - $effectValue);
                }
            }
        }

        // Les cartes ne peuvent jamais franchir le hard cap.
        $maxQ = min($maxQ, $qHardCap);

        return [
            'max_base_bet' => $maxBaseBet,
            'max_q' => $maxQ,
            'min_cooldown' => $minCooldown,
        ];
    }

    public function getActiveLimitsAttribute()
    {
        return $this->getActiveLimits();
    }

    public function syncLimitsToBlockchain()
    {
        if (empty($this->wallet_address)) {
            return null;
        }

        $nodeUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
        $limits = $this->getActiveLimits();

        // Calculate expiry
        $activeCards = $this->userCards()
            ->with('card')
            ->where('status', 'available')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $expiry = 0;
        $hasSessionCard = false;
        $maxExpiresAt = null;

        foreach ($activeCards as $userCard) {
            $card = $userCard->card;
            if (!$card || !$card->is_active) {
                continue;
            }
            if ($card->duration_type === 'sessions') {
                $hasSessionCard = true;
            } elseif ($card->duration_type === 'time' && $userCard->expires_at) {
                $ts = $userCard->expires_at->timestamp;
                if ($maxExpiresAt === null || $ts > $maxExpiresAt) {
                    $maxExpiresAt = $ts;
                }
            }
        }

        if ($hasSessionCard) {
            // For session-based cards (which don't have temporal expiry), use a huge timestamp as the expiry value.
            // On the contract: any value >= 1e9 is a timestamp. So we can use 9999999999.
            $expiry = 9999999999;
        } elseif ($maxExpiresAt !== null) {
            $expiry = $maxExpiresAt;
        }

        // min_cooldown in active limits is in minutes, contract expects seconds
        // Subtract 20 seconds as safety margin for transaction processing time
        // Safety margin for blockchain transaction processing time (configurable)
        $safetyMarginSeconds = (int) config('game.cooldown_safety_margin_seconds', 20);
        $minCooldownSeconds = max(0, ($limits['min_cooldown'] * 60) - $safetyMarginSeconds);

        // --- COALESCENCE (perf §A1) ---
        // Les limites effectives (max_base_bet, max_q, min_cooldown, expiry) sont
        // déterministes pour un état de cartes donné. Si elles n'ont pas changé
        // depuis la dernière synchronisation on-chain, on saute l'appel setUserLimits.
        // Cela évite de saturer la file nonce du bridge avec des txs redondantes
        // (goulot documenté HANDOVER.md §12.4 : 97 jobs/min produits vs 14 consommés).
        $effectiveHash = hash('sha256', implode('|', [
            (string) $limits['max_base_bet'],
            (string) $limits['max_q'],
            (string) $minCooldownSeconds,
            (string) $expiry,
        ]));

        if ($this->last_synced_limits_hash === $effectiveHash) {
            return null; // Limites inchangées : rien à synchroniser.
        }

        $result = app(\App\Helpers\Web3Helper::class)->setUserLimits(
            $nodeUrl,
            $this->wallet_address,
            $limits['max_base_bet'],
            $limits['max_q'],
            $minCooldownSeconds,
            $expiry
        );

        // Ne mémoriser le hash qu'en cas de succès : en cas d'échec, le job
        // sera re-tenté (tries=3) et re-synchronisera les limites.
        $this->last_synced_limits_hash = $effectiveHash;
        $this->save();

        return $result;
    }
}
