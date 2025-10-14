<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralReward;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    /**
     * Gère toute la logique de validation d'un parrainage pour un utilisateur donné.
     * C'est la fonction centrale qui sera appelée après un événement clé (comme un achat).
     *
     * @param User $referredUser L'utilisateur qui a déclenché l'événement (le filleul).
     */
    public function processReferralValidation(User $referredUser)
    {


        // Cherche si cet utilisateur a un parrainage en attente
        $referral = Referral::where('referred_id', $referredUser->id)
            ->where('status', 'pending')
            ->first();

        // Si pas de parrainage, on s'arrête là.
        if (!$referral) {
            return; // Pas de parrainage à valider
        }

        // 2. Gérer la validation et la récompense pour le PARRAIN
        $referral->update(['status' => 'validated']);
        
        $referrer = $referral->referrer;
        $totalValidated = Referral::where('referrer_id', $referrer->id)->where('status', 'validated')->count();
        $milestones = [ 1 => 1, 3 => 1, 5 => 1, 11 => 1, 50 => 1, 100 => 1 ];

        $alreadyRewarded = ReferralReward::where('referrer_id', $referrer->id)
            ->where('milestone_reached', $totalValidated)
            ->exists();

        if (array_key_exists($totalValidated, $milestones) && !$alreadyRewarded) {
            $rewardAmount = $milestones[$totalValidated];
            ReferralReward::create([
                'referrer_id' => $referrer->id,
                'milestone_reached' => $totalValidated,
                'reward_tokens' => $rewardAmount,
            ]);
            $referrer->increment('token_balance', $rewardAmount);
            Log::info('🎁 Reward granted to referrer', ['referrer_id' => $referrer->id, 'milestone' => $totalValidated]);
        }
    }
}