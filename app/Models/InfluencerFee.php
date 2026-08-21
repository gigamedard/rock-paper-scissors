<?php

// === CORRECTION ICI ===
namespace App\Models; 
// ======================

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfluencerFee extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'language_code',
        'fee_amount_avax',
    ];

    /**
     * Définit la relation avec l'utilisateur qui a généré les frais.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}