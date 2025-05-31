<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;


class PreMove extends Model
{
    use HasFactory;

    protected $table = 'pre_moves';

    protected $fillable = [
        'user_id',
        'moves',
        'hashed_moves',
        'nonce',
        'current_index',
        'session_first_pool_id',
        'cid'
    ];

    // A pre-move belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::deleting(function ($Premove) {
            Log::info('Premove is being deleted', [
                'Premove_id' => $Premove->id,
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
                'method' => request()->method(),
                'input' => request()->all(),
                'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))->pluck('function')
            ]);
        });
    }
}