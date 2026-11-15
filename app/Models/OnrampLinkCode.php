<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnrampLinkCode extends Model
{
    protected $fillable = ['user_id', 'code', 'expires_at', 'used_at'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
