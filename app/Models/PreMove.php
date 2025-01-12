<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    // A pre-move belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}