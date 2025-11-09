<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Pool;
use App\Models\PreMove;
use App\Models\FHist;
use App\Services\HistoricalFightService;
use Illuminate\Support\Facades\Log;
use App\Helpers\web3Helper; // Assuming you have a web3Helper class for checking pre-moves

class Fight extends Model
{
    use HasFactory;

    protected $table = 'fights';

    protected $fillable = [
        'user1_id',
        'user2_id',
        'status',
        'result',
        'user1_chosed',
        'user2_chosed',
        'base_bet_amount',
        'max_bet_amount',
        'pool_id',
    
    
    ];



    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }


    public function fHist()
    {
        return $this->hasOne(FHist::class, 'fight_id');
    }





    
}