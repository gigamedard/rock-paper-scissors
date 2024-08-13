<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fight extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fights';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user1_id',
        'user2_id',
        'status',
        'result',
        'user1_chosed',
        'user2_chosed',
    ];

    /**
     * Get the user1 that owns the fight.
     */
    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Get the user2 that owns the fight.
     */
    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }
}
