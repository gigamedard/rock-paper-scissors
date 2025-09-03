<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InfluencerPool;

class CreateInfluencerPool extends Command
{
    protected $signature = 'influencer:create-pool 
                            {name : Pool name}
                            {language : Pool language}
                            {--milestone=5000 : Individual milestone}
                            {--pool-milestone=30000 : Pool milestone}
                            {--reward=0 : Reward amount in AVAX}';
    
    protected $description = 'Create a new influencer pool';

    public function handle()
    {
        $name = $this->argument('name');
        $language = $this->argument('language');
        $milestone = $this->option('milestone');
        $poolMilestone = $this->option('pool-milestone');
        $rewardAmount = $this->option('reward');

        try {
            $pool = InfluencerPool::create([
                'name' => $name,
                'language' => $language,
                'milestone' => $milestone,
                'pool_milestone' => $poolMilestone,
                'reward_amount' => $rewardAmount,
                'is_active' => true
            ]);

            $this->info('Pool d\'influenceur créé avec succès!');
            $this->info('ID: ' . $pool->id);
            $this->info('Nom: ' . $pool->name);
            $this->info('Langue: ' . $pool->language);
            $this->info('Objectif individuel: ' . $pool->milestone);
            $this->info('Objectif du pool: ' . $pool->pool_milestone);
            $this->info('Récompense: ' . $pool->reward_amount . ' AVAX');

            return 0;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la création du pool: ' . $e->getMessage());
            return 1;
        }
    }
}

