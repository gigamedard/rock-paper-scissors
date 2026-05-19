<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'effect_type',
        'effect_value',
        'duration_type',
        'duration_value',
        'price',
        'currency',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'effect_value' => 'float',
        'price' => 'float',
        'is_active' => 'boolean',
    ];

    public function userCards()
    {
        return $this->hasMany(UserCard::class);
    }
}
