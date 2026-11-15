<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnrampLink extends Model
{
    protected $fillable = ['user_id', 'channel', 'chat_id', 'username', 'terms_accepted_at', 'terms_version'];
    protected $casts = ['terms_accepted_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted_at !== null;
    }
}
