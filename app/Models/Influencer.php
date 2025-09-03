<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Influencer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pool_id',
        'is_eligible',
        'has_claimed'
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'has_claimed' => 'boolean'
    ];

    /**
     * Get the user associated with this influencer.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pool this influencer belongs to.
     */
    public function pool()
    {
        return $this->belongsTo(InfluencerPool::class, 'pool_id');
    }

    /**
     * Get the stats for this influencer.
     */
    public function stats()
    {
        return $this->hasOne(InfluencerStat::class);
    }

    /**
     * Check if the influencer has reached their personal milestone.
     */
    public function hasReachedMilestone()
    {
        return $this->stats && $this->stats->referral_count >= $this->pool->milestone;
    }

    /**
     * Check if the influencer can claim rewards.
     */
    public function canClaimReward()
    {
        return $this->is_eligible 
            && !$this->has_claimed 
            && $this->hasReachedMilestone() 
            && $this->pool->isPoolMilestoneReached();
    }
}

