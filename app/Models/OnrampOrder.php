<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnrampOrder extends Model
{
    public const STATUS_PENDING_DEPOSIT = 'pending_deposit';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'reference', 'user_id', 'channel', 'chat_id', 'network', 'package_code',
        'usdt_amount', 'avaxe_amount', 'deposit_address', 'tx_hash', 'confirmations',
        'status', 'admin_id', 'admin_note', 'credit_tx_hash', 'submitted_at',
        'approved_at', 'credited_at', 'rejected_at',
    ];

    protected $casts = [
        'usdt_amount' => 'decimal:8',
        'avaxe_amount' => 'decimal:8',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'credited_at' => 'datetime',
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
        return $this->hasMany(OnrampEvent::class);
    }
}
