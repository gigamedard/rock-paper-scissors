<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Models\Pool;
use App\Models\User;



class Pool extends Model
{   
    use HasFactory;
    protected $table = 'pools';

    protected $fillable = [
        'server_pool_id',
        'salt',
        'pool_size',
        'pool_id',
        'base_bet'
    ];

    // Define the many-to-many relationship with users
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pool_user', 'pool_id', 'user_id');
    }
    // A pool has many fights
    public function fights(): HasMany
    {
        return $this->hasMany(Fight::class);
    }

    private function removeUserFromPool(int $userId): void
    {

        $this->users()->detach($userId);
    }
}