<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralReward;
use Illuminate\Support\Facades\Log;
use App\Models\InfluencerStat; // <-- AJOUTE CETTE LIGNE

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
        // 1. On le marque comme éligible pour parrainer à son tour.
        if (!$referredUser->is_eligible_to_refer) {
            $referredUser->update(['is_eligible_to_refer' => true]);
            Log::info('User is now eligible to refer', ['user_id' => $referredUser->id]);
        }

        // 2. Cherche si cet utilisateur a un parrainage en attente
        $referral = Referral::where('referred_id', $referredUser->id)
            ->where('status', 'pending')
            ->first();

        // Si pas de parrainage, on s'arrête là.
        if (!$referral) {
            return; // Pas de parrainage à valider
        }

        // 3. On vérifie si le filleul n'a pas déjà reçu son bonus et s'il a bien un parrainage.
        if (!$referredUser->has_received_signup_bonus) {
            // Il est venu avec un code, on lui donne son bonus de 1 SNT !
            $referredUser->increment('token_balance', 1);
            $referredUser->update(['has_received_signup_bonus' => true]);

            Log::info('🎉 Signup bonus granted to referred user', [
                'user_id' => $referredUser->id,
                'bonus_amount' => 1,
                'new_token_balance' => $referredUser->fresh()->token_balance
            ]);
        }

        // 4. Gérer la validation et la récompense pour le PARRAIN
        $referral->update(['status' => 'validated']);
        
        $referrer = $referral->referrer;

        // On vérifie si le parrain est aussi un influenceur
        if ($referrer->influencer) {
            Log::info('Le parrain est un influenceur. Mise à jour de ses stats.', ['user_id' => $referrer->id]);
            
            // On trouve ou on crée sa feuille de statistiques
            $stats = InfluencerStat::firstOrCreate(
                ['influencer_id' => $referrer->influencer->id],
                ['referral_count' => 0, 'total_avax_spent' => 0]
            );
            
            // On utilise la méthode de ton modèle InfluencerStat
            $stats->incrementReferralCount(1);
            
            Log::info('Stats de l\'influenceur mises à jour', ['influencer_id' => $referrer->influencer->id, 'new_count' => $stats->fresh()->referral_count]);
        }

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
            Log::info('🎁 Reward granted to referrer', ['referrer_id' => $referrer->id, 'milestone' => $totalValidated, 'new_token_balance' => $referrer->fresh()->token_balance]);
        }
    }

}