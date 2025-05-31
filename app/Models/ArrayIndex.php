<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArrayIndex extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'array_name',
        'current_index',
    ];
}