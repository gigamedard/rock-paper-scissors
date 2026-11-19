<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfframpOrder extends Model
{
    public const STATUS_PENDING_DEPOSIT = 'pending_deposit';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAYING = 'paying';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PAYOUT_DEFERRED = 'payout_deferred';
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    protected $fillable = [
        'reference', 'user_id', 'channel', 'chat_id', 'payout_network', 'payout_address',
        'avaxe_amount', 'usdt_amount', 'rate', 'fee_percent', 'status',
        'deposit_address', 'deposit_index', 'deposit_amount', 'tx_hash', 'confirmations',
        'submitted_at', 'deposit_detected_at', 'deposit_confirmed_at',
        'admin_id', 'admin_note', 'approved_at',
        'payout_tx_hash', 'payout_attempts', 'payout_started_at', 'payout_last_error', 'paid_at',
        'sweep_status', 'sweep_tx_hash', 'sweep_amount', 'sweep_attempts',
        'sweep_started_at', 'sweep_last_error', 'swept_at', 'expires_at', 'rejected_at',
    ];

    protected $casts = [
        'avaxe_amount' => 'decimal:8',
        'usdt_amount' => 'decimal:8',
        'rate' => 'decimal:8',
        'fee_percent' => 'decimal:4',
        'deposit_amount' => 'decimal:8',
        'sweep_amount' => 'decimal:8',
        'submitted_at' => 'datetime',
        'deposit_detected_at' => 'datetime',
        'deposit_confirmed_at' => 'datetime',
        'approved_at' => 'datetime',
        'payout_started_at' => 'datetime',
        'paid_at' => 'datetime',
        'sweep_started_at' => 'datetime',
        'swept_at' => 'datetime',
        'expires_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function events()
    {
        return $this->hasMany(OfframpEvent::class);
    }
}
