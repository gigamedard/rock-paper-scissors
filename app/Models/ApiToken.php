<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'token', 'abilities', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function generateForUser($user, $ttl = null)
    {
        $plain = Str::random(60);

        $token = static::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plain),
            'abilities'  => '*',
            'expires_at' => $ttl ? now()->addMinutes($ttl) : null,
        ]);

        return $plain; // return raw token for client
    }

    public function isValid()
    {
        return !$this->expires_at || $this->expires_at->isFuture();
    }
}
