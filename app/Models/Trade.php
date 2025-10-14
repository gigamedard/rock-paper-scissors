<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'blockchain_trade_id',
        'seller_wallet_address',
        'snt_amount',
        'avax_amount',
        'status',
        'expires_at',
        'buyer_wallet_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}