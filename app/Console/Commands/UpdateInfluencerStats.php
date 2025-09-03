<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Influencer;
use App\Models\InfluencerStat;
use App\Models\Referral;

class UpdateInfluencerStats extends Command
{
    protected $signature = 'influencer:update-stats 
                            {--all : Update stats for all influencers}
                            {--email= : Update stats for specific user email}';
    
    protected $description = 'Update influencer statistics based on referrals';

    public function handle()
    {
        $updateAll = $this->option('all');
        $email = $this->option('email');

        if (!$updateAll && !$email) {
            $this->error('Vous devez spécifier --all ou --email=<email>');
            return 1;
        }

        try {
            if ($updateAll) {
                $this->updateAllInfluencers();
            } else {
                $this->updateInfluencerByEmail($email);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la mise à jour: ' . $e->getMessage());
            return 1;
        }
    }

    private function updateAllInfluencers()
    {
        $influencers = Influencer::with(['user', 'stats'])->get();
        
        $this->info('Mise à jour des statistiques pour ' . $influencers->count() . ' influenceurs...');
        
        $progressBar = $this->output->createProgressBar($influencers->count());
        $progressBar->start();

        foreach ($influencers as $influencer) {
            $this->updateInfluencerStats($influencer);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\nMise à jour terminée!");
    }

    private function updateInfluencerByEmail(string $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error('Utilisateur non trouvé avec l\'email: ' . $email);
            return;
        }

        $influencer = $user->influencer;
        if (!$influencer) {
            $this->error('L\'utilisateur n\'est pas un influenceur.');
            return;
        }

        $this->updateInfluencerStats($influencer);
        $this->info('Statistiques mises à jour pour: ' . $user->name);
    }

    private function updateInfluencerStats(Influencer $influencer)
    {
        // Count validated referrals for this user
        $validatedReferrals = Referral::where('referrer_id', $influencer->user_id)
            ->where('status', 'validated')
            ->count();

        // Calculate total AVAX spent by referred users (this is a simplified calculation)
        $totalAvaxSpent = Referral::where('referrer_id', $influencer->user_id)
            ->where('status', 'validated')
            ->join('users', 'referrals.referred_id', '=', 'users.id')
            ->sum('users.balance'); // This is simplified - in reality you'd track actual spending

        // Update or create stats
        $stats = $influencer->stats;
        if (!$stats) {
            $stats = InfluencerStat::create([
                'influencer_id' => $influencer->id,
                'referral_count' => $validatedReferrals,
                'total_avax_spent' => $totalAvaxSpent
            ]);
        } else {
            $stats->update([
                'referral_count' => $validatedReferrals,
                'total_avax_spent' => $totalAvaxSpent
            ]);
        }
    }
}

