<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Batch extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pool_size', // Add pool_size
        'first_pool_id',
        'last_pool_id',
        'number_of_pools',
        'max_size',
        'status', // e.g., 'waiting', 'running', 'settled'
        'iteration_count',
        'max_iterations',
    ];
}
