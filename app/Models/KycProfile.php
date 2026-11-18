<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycProfile extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'full_name', 'date_of_birth', 'country', 'address', 'city',
        'postal_code', 'id_type', 'id_number', 'source_of_funds', 'occupation',
        'wallet_address', 'status', 'risk_level', 'reviewer_id', 'notes',
        'consent_at', 'submitted_at', 'reviewed_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'id_number' => 'encrypted',
        'consent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected $hidden = ['id_number'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function idLast4(): string
    {
        $value = (string) $this->id_number;
        return $value === '' ? '' : substr($value, -4);
    }
}
