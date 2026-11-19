<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaveOrder extends Model
{
    public const DIRECTION_BUY_USDT = 'buy_usdt';
    public const DIRECTION_SELL_USDT = 'sell_usdt';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PENDING_DEPOSIT = 'pending_deposit';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PAID = 'paid';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAYING = 'paying';
    public const STATUS_PAYOUT_DEFERRED = 'payout_deferred';
    public const STATUS_MANUAL_REVIEW = 'manual_review';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'reference', 'mobile_user_id', 'channel', 'chat_id', 'direction', 'operator', 'country', 'currency',
        'fiat_amount', 'usdt_amount', 'usdt_gross', 'rate', 'fee_percent', 'status',
        'payment_phone', 'payout_phone', 'payout_network', 'deposit_address', 'payment_url',
        'payment_ref', 'usdt_deposit_tx_hash', 'usdt_payout_tx_hash', 'payout_attempts', 'payout_started_at', 'payout_last_error', 'fiat_tx_ref',
        'admin_id', 'admin_note', 'verified_at', 'paid_at', 'rejected_at', 'expires_at',
    ];

    protected $casts = [
        'fiat_amount' => 'decimal:2',
        'usdt_amount' => 'decimal:8',
        'usdt_gross' => 'decimal:8',
        'rate' => 'decimal:8',
        'fee_percent' => 'decimal:4',
        'verified_at' => 'datetime',
        'payout_started_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function mobileUser()
    {
        return $this->belongsTo(MobileCryptoUser::class, 'mobile_user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function events()
    {
        return $this->hasMany(WaveEvent::class);
    }
}
