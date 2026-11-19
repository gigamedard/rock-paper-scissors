<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileCryptoUser extends Model
{
    public const KYC_UNVERIFIED = 'unverified';
    public const KYC_PENDING = 'pending';
    public const KYC_APPROVED = 'approved';
    public const KYC_REJECTED = 'rejected';

    protected $fillable = [
        'telegram_chat_id', 'telegram_username', 'full_name', 'phone', 'country',
        'kyc_status', 'allowlist', 'payout_address', 'terms_accepted_at', 'last_seen_at', 'admin_note',
    ];

    protected $casts = [
        'allowlist' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(WaveOrder::class, 'mobile_user_id');
    }

    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted_at !== null;
    }

    public function isReady(): bool
    {
        return $this->hasAcceptedTerms();
    }
}
