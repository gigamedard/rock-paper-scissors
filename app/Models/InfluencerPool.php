<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfluencerPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'language',
        'milestone',
        'pool_milestone',
        'reward_amount',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reward_amount' => 'decimal:8'
    ];

    /**
     * Get the influencers in this pool.
     */
    public function influencers()
    {
        return $this->hasMany(Influencer::class, 'pool_id');
    }

    /**
     * Get the total referral count for this pool.
     */
    public function getTotalReferralCountAttribute()
    {
        return $this->influencers()
            ->join('influencer_stats', 'influencers.id', '=', 'influencer_stats.influencer_id')
            ->sum('influencer_stats.referral_count');
    }

    /**
     * Check if the pool milestone is reached.
     */
    public function isPoolMilestoneReached()
    {
        return $this->total_referral_count >= $this->pool_milestone;
    }

    /**
     * Get eligible influencers for rewards.
     */
    public function getEligibleInfluencers()
    {
        return $this->influencers()
            ->where('is_eligible', true)
            ->whereHas('stats', function ($query) {
                $query->where('referral_count', '>=', $this->milestone);
            })
            ->where('has_claimed', false);
    }
}

