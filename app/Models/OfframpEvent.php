<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfframpEvent extends Model
{
    protected $fillable = ['offramp_order_id', 'type', 'actor_type', 'actor_id', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function order()
    {
        return $this->belongsTo(OfframpOrder::class, 'offramp_order_id');
    }
}
