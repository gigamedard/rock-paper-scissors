<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use App\Helpers\Web3Helper; // Assuming you have a Web3Helper class for sorting

use App\Models\Pool;
use App\Models\User;
use App\Models\Fight;



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

    // Define the many-to-many relationship with users
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pool_id');
    }
    // A pool has many fights
    public function fights(): HasMany
    {
        return $this->hasMany(Fight::class);
    }


    
}