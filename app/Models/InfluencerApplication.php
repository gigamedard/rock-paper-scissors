<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfluencerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pseudo',
        'social_links',
        'status',
        'admin_notes'
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
