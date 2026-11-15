<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnrampEvent extends Model
{
    protected $fillable = ['onramp_order_id', 'type', 'actor_type', 'actor_id', 'payload'];
    protected $casts = ['payload' => 'array'];

    public function order()
    {
        return $this->belongsTo(OnrampOrder::class, 'onramp_order_id');
    }
}
