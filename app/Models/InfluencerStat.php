<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfluencerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'influencer_id',
        'referral_count',
        'total_avax_spent'
    ];

    protected $casts = [
        'total_avax_spent' => 'decimal:8'
    ];

    /**
     * Get the influencer these stats belong to.
     */
    public function influencer()
    {
        return $this->belongsTo(Influencer::class);
    }

    /**
     * Increment the referral count.
     */
    public function incrementReferralCount($count = 1)
    {
        $this->increment('referral_count', $count);
    }

    /**
     * Add to the total AVAX spent.
     */
    public function addAvaxSpent($amount)
    {
        $this->increment('total_avax_spent', $amount);
    }
}

