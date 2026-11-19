<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaveEvent extends Model
{
    protected $fillable = ['wave_order_id', 'type', 'actor_type', 'actor_id', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function order()
    {
        return $this->belongsTo(WaveOrder::class, 'wave_order_id');
    }
}
