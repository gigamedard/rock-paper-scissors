<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalData extends Model
{
    use HasFactory;

    protected $table = 'historical_data';

    protected $fillable = [
        'user1_wallet_address',
        'user1_move_index',
        'user2_wallet_address',
        'user2_move_index',
        'winner_index',
        'prize',
        'encryption',
    ];
}
