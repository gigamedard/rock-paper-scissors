<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WavePortalEvent extends Model
{
    protected $fillable = [
        'transaction_id', 'device_id', 'direction', 'amount', 'currency',
        'occurred_at', 'status', 'matched_order_id', 'error', 'received_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(WaveOrder::class, 'matched_order_id');
    }
}
