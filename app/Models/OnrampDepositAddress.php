<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnrampDepositAddress extends Model
{
    protected $fillable = ['derivation_index', 'address', 'order_id', 'assigned_at'];

    protected $casts = [
        'derivation_index' => 'integer',
        'assigned_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(OnrampOrder::class, 'order_id');
    }
}
