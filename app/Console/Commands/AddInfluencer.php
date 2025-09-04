<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\InfluencerPool;
use App\Models\Influencer;
use App\Models\InfluencerStat;
use Illuminate\Support\Facades\DB;

class AddInfluencer extends Command
{
    protected $signature = 'influencer:add 
                            {email : User email}
                            {pool-id : Pool ID}
                            {--eligible=false : Set as eligible}';
    
    protected $description = 'Add a user to an influencer pool';

    public function handle()
    {
        $email = $this->argument('email');
        $poolId = $this->argument('pool-id');
        $isEligible = $this->option('eligible');

        try {
            // Find user by email
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error('Utilisateur non trouvé avec l\'email: ' . $email);
                return 1;
            }

            // Find pool
            $pool = InfluencerPool::find($poolId);
            if (!$pool) {
                $this->error('Pool non trouvé avec l\'ID: ' . $poolId);
                return 1;
            }

            // Check if user is already an influencer in this pool
            $existingInfluencer = Influencer::where('user_id', $user->id)
                ->where('pool_id', $poolId)
                ->first();

            if ($existingInfluencer) {
                $this->error('L\'utilisateur est déjà dans ce pool d\'influenceurs.');
                return 1;
            }

            DB::transaction(function () use ($user, $poolId, $isEligible) {
                // Create influencer record
                $influencer = Influencer::create([
                    'user_id' => $user->id,
                    'pool_id' => $poolId,
                    'is_eligible' => $isEligible
                ]);

                // Create initial stats record
                InfluencerStat::create([
                    'influencer_id' => $influencer->id,
                    'referral_count' => 0,
                    'total_avax_spent' => 0
                ]);
            });

            $this->info('Influenceur ajouté avec succès!');
            $this->info('Utilisateur: ' . $user->name . ' (' . $user->email . ')');
            $this->info('Pool: ' . $pool->name);
            $this->info('Éligible: ' . ($isEligible ? 'Oui' : 'Non'));

            return 0;
        } catch (\Exception $e) {
            $this->error('Erreur lors de l\'ajout de l\'influenceur: ' . $e->getMessage());
            return 1;
        }
    }
}

