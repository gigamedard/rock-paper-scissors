<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralReward;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($referredUser) {
            // Lock the referred user to prevent concurrent modifications
            $referredUser = User::where('id', $referredUser->id)->lockForUpdate()->first();

            // 1. On le marque comme éligible pour parrainer à son tour.
            if (!$referredUser->is_eligible_to_refer) {
                $referredUser->update(['is_eligible_to_refer' => true]);
                Log::info('User is now eligible to refer', ['user_id' => $referredUser->id]);
            }

            // 2. Cherche si cet utilisateur a un parrainage en attente (avec verrou)
            $referral = Referral::where('referred_id', $referredUser->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            // Si pas de parrainage, on s'arrête là.
            if (!$referral) {
                return; // Pas de parrainage à valider
            }

            // 3. On vérifie si le filleul n'a pas déjà reçu son bonus et s'il a bien un parrainage.
            $refereeBonus = (float) config('economy.referral.referee_bonus', 40);
            if (!$referredUser->has_received_signup_bonus) {
                // Bonuse de jeu NON VENDABLE (locked_balance) : de quoi acheter des cartes.
                $referredUser->increment('locked_balance', $refereeBonus);
                $referredUser->update(['has_received_signup_bonus' => true]);

                Log::info('🎉 Signup bonus granted to referred user (locked credit)', [
                    'user_id' => $referredUser->id,
                    'bonus_amount' => $refereeBonus,
                    'new_locked_balance' => $referredUser->fresh()->locked_balance
                ]);
            }

            // 4. Gérer la validation et la récompense pour le PARRAIN
            $referral->update(['status' => 'validated']);
            event(new \App\Events\ReferralValidated($referral));
            
            // Lock the referrer
            $referrer = User::where('id', $referral->referrer_id)->lockForUpdate()->first();

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
            $milestones = config('economy.referral.milestones', [
                1 => 40, 3 => 150, 5 => 400, 11 => 1000, 50 => 5000, 100 => 15000,
            ]);

            $alreadyRewarded = ReferralReward::where('referrer_id', $referrer->id)
                ->where('milestone_reached', $totalValidated)
                ->exists();

            if (array_key_exists($totalValidated, $milestones) && !$alreadyRewarded) {
                $rewardAmount = (float) $milestones[$totalValidated];
                ReferralReward::create([
                    'referrer_id' => $referrer->id,
                    'milestone_reached' => $totalValidated,
                    'reward_tokens' => $rewardAmount,
                ]);
                // Crédit de jeu NON VENDABLE (locked_balance) : utilisable au shop.
                $referrer->increment('locked_balance', $rewardAmount);
                Log::info('🎁 Reward granted to referrer (locked credit)', ['referrer_id' => $referrer->id, 'milestone' => $totalValidated, 'new_locked_balance' => $referrer->fresh()->locked_balance]);
            }
        });
    }

}