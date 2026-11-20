<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwapOrder extends Model
{
    public const USDT_TO_AVAXE = 'usdt_to_avaxe';
    public const AVAXE_TO_USDT = 'avaxe_to_usdt';

    public const PENDING_DEPOSIT = 'pending_deposit';
    public const PENDING_VERIFICATION = 'pending_verification';
    public const PAYING = 'paying';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const MANUAL_REVIEW = 'manual_review';

    protected $fillable = [
        'reference', 'direction', 'network', 'amount_in', 'amount_out', 'rate',
        'deposit_address', 'payout_address', 'status', 'deposit_tx_hash',
        'confirmations', 'payout_tx_hash', 'error', 'verified_at', 'completed_at',
    ];

    protected $casts = [
        'amount_in' => 'decimal:8',
        'amount_out' => 'decimal:8',
        'rate' => 'decimal:8',
        'verified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
