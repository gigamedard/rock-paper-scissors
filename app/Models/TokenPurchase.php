<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'quantity',
        'source',
        'tx_hash',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}