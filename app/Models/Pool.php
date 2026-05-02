<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pool extends Model
{
    use HasFactory;
    protected $table = 'pools';

    protected $fillable = [
        'salt',
        'pool_size',
        'pool_id',
        'base_bet',
        'premove_cids',
        'status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pool_id');
    }

    public function fights(): HasMany
    {
        return $this->hasMany(Fight::class);
    }

    public function match(): void
    {
        app(\App\Services\MatchmakingEngine::class)->process($this->id);
    }
}
