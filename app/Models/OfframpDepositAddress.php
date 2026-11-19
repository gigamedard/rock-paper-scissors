<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfframpDepositAddress extends Model
{
    protected $fillable = ['derivation_index', 'address', 'order_id', 'assigned_at'];

    protected $casts = [
        'derivation_index' => 'integer',
        'assigned_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(OfframpOrder::class, 'order_id');
    }
}
