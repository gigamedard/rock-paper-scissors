<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Referral;
use App\Models\InfluencerPool;
use App\Models\Influencer;
use App\Models\InfluencerStat;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Création des données de test...');

        // 1. Créer des utilisateurs de test
        $this->createTestUsers();
        
        // 2. Créer des parrainages
        $this->createReferrals();
        
        // 3. Créer des pools d'influenceurs
        $this->createInfluencerPools();
        
        // 4. Créer des influenceurs et leurs statistiques
        $this->createInfluencers();

        $this->command->info('✅ Données de test créées avec succès !');
    }

    private function createTestUsers()
    {
        $this->command->info('👥 Création des utilisateurs de test...');

        // Utilisateur principal pour les tests
        $mainUser = User::create([
            'name' => 'Test User',
            'email' => 'test@rockpaperscissors.com',
            'password' => Hash::make('password123'),
            'wallet_address' => '0x1111111111111111111111111111111111111111',
            'referral_code' => 'REF-TEST01',
            'balance' => 500,
            'total_referral_rewards' => 1200,
            'successful_referrals' => 12,
        ]);

        // Créer des utilisateurs pour le classement
        $topReferrers = [
            [
                'name' => 'CryptoMaster',
                'email' => 'cryptomaster@example.com',
                'wallet_address' => '0x1234567890123456789012345678901234567890',
                'referral_code' => 'REF-CRYPTO',
                'balance' => 1000,
                'successful_referrals' => 45,
                'total_referral_rewards' => 4500,
            ],
            [
                'name' => 'BlockchainPro',
                'email' => 'blockchainpro@example.com',
                'wallet_address' => '0x9876543210987654321098765432109876543210',
                'referral_code' => 'REF-BLOCK',
                'balance' => 800,
                'successful_referrals' => 32,
                'total_referral_rewards' => 3200,
            ],
            [
                'name' => 'Web3Guru',
                'email' => 'web3guru@example.com',
                'wallet_address' => '0x5555555555555555555555555555555555555555',
                'referral_code' => 'REF-WEB3',
                'balance' => 600,
                'successful_referrals' => 28,
                'total_referral_rewards' => 2800,
            ],
            [
                'name' => 'DeFiExpert',
                'email' => 'defiexpert@example.com',
                'wallet_address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'referral_code' => 'REF-DEFI',
                'balance' => 400,
                'successful_referrals' => 22,
                'total_referral_rewards' => 2200,
            ],
            [
                'name' => 'NFTCollector',
                'email' => 'nftcollector@example.com',
                'wallet_address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'referral_code' => 'REF-NFT',
                'balance' => 350,
                'successful_referrals' => 18,
                'total_referral_rewards' => 1800,
            ],
        ];

        foreach ($topReferrers as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password123'),
                'wallet_address' => $userData['wallet_address'],
                'referral_code' => $userData['referral_code'],
                'balance' => $userData['balance'],
                'successful_referrals' => $userData['successful_referrals'],
                'total_referral_rewards' => $userData['total_referral_rewards'],
            ]);
        }

        // Créer des utilisateurs parrainés
        for ($i = 1; $i <= 20; $i++) {
            User::create([
                'name' => "Referred User {$i}",
                'email' => "referred{$i}@example.com",
                'password' => Hash::make('password123'),
                'wallet_address' => '0x' . str_pad(dechex($i + 100), 40, '0', STR_PAD_LEFT),
                'balance' => rand(50, 300),
                'referred_by' => rand(1, 6), // Référé par un des top referrers
            ]);
        }

        $this->command->info("✅ {$this->getUserCount()} utilisateurs créés");
    }

    private function createReferrals()
    {
        $this->command->info('🤝 Création des parrainages...');

        $referrers = User::whereNotNull('referral_code')->get();
        $referred = User::whereNotNull('referred_by')->get();

        $referralCount = 0;

        foreach ($referred as $referredUser) {
            $referrer = $referrers->find($referredUser->referred_by);
            
            if ($referrer) {
                $status = rand(1, 10) <= 8 ? 'validated' : 'pending'; // 80% validés
                
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $referredUser->id,
                    'referral_code' => $referrer->referral_code,
                    'status' => $status,
                    'reward_amount' => 100,
                    'validated_at' => $status === 'validated' ? now()->subDays(rand(1, 30)) : null,
                ]);
                
                $referralCount++;
            }
        }

        // Créer quelques parrainages supplémentaires pour l'utilisateur de test
        $testUser = User::where('email', 'test@rockpaperscissors.com')->first();
        for ($i = 1; $i <= 5; $i++) {
            $referredUser = User::create([
                'name' => "Test Referred {$i}",
                'email' => "testreferred{$i}@example.com",
                'password' => Hash::make('password123'),
                'wallet_address' => '0x' . str_pad(dechex($i + 200), 40, '0', STR_PAD_LEFT),
                'balance' => rand(100, 500),
                'referred_by' => $testUser->id,
            ]);

            Referral::create([
                'referrer_id' => $testUser->id,
                'referred_id' => $referredUser->id,
                'referral_code' => $testUser->referral_code,
                'status' => $i <= 3 ? 'validated' : 'pending',
                'reward_amount' => 100,
                'validated_at' => $i <= 3 ? now()->subDays(rand(1, 15)) : null,
            ]);
            
            $referralCount++;
        }

        $this->command->info("✅ {$referralCount} parrainages créés");
    }

    private function createInfluencerPools()
    {
        $this->command->info('🏆 Création des pools d\'influenceurs...');

        $pools = [
            [
                'name' => 'Influenceurs Français',
                'language' => 'français',
                'milestone' => 5000,
                'pool_milestone' => 30000,
                'reward_amount' => 10,
                'current_referrals' => 24500,
                'eligible_influencers' => 5,
            ],
            [
                'name' => 'English Influencers',
                'language' => 'english',
                'milestone' => 7500,
                'pool_milestone' => 50000,
                'reward_amount' => 25,
                'current_referrals' => 32100,
                'eligible_influencers' => 8,
            ],
            [
                'name' => 'Influenciadores Españoles',
                'language' => 'español',
                'milestone' => 4000,
                'pool_milestone' => 20000,
                'reward_amount' => 8,
                'current_referrals' => 15800,
                'eligible_influencers' => 4,
            ],
            [
                'name' => 'Deutsche Influencer',
                'language' => 'deutsch',
                'milestone' => 6000,
                'pool_milestone' => 35000,
                'reward_amount' => 15,
                'current_referrals' => 18200,
                'eligible_influencers' => 3,
            ],
        ];

        foreach ($pools as $poolData) {
            InfluencerPool::create($poolData);
        }

        $this->command->info("✅ " . count($pools) . " pools d'influenceurs créés");
    }

    private function createInfluencers()
    {
        $this->command->info('🌟 Création des influenceurs et statistiques...');

        $pools = InfluencerPool::all();
        $topUsers = User::whereNotNull('referral_code')
            ->orderBy('successful_referrals', 'desc')
            ->take(15)
            ->get();

        $influencerCount = 0;

        foreach ($pools as $pool) {
            // Assigner des influenceurs à chaque pool
            $poolInfluencers = $topUsers->random(rand(6, 10));
            
            foreach ($poolInfluencers as $index => $user) {
                $isEligible = $index < $pool->eligible_influencers;
                
                $influencer = Influencer::create([
                    'user_id' => $user->id,
                    'pool_id' => $pool->id,
                    'is_eligible' => $isEligible,
                    'has_claimed_reward' => $isEligible && rand(1, 10) <= 3, // 30% ont réclamé
                    'claimed_at' => $isEligible && rand(1, 10) <= 3 ? now()->subDays(rand(1, 10)) : null,
                ]);

                // Créer les statistiques
                $referralCount = $isEligible ? 
                    rand($pool->milestone, $pool->milestone + 2000) : 
                    rand(1000, $pool->milestone - 500);

                InfluencerStat::create([
                    'influencer_id' => $influencer->id,
                    'referral_count' => $referralCount,
                    'total_avax_spent' => round($referralCount * 0.003 + rand(1, 10), 2),
                    'active_referrals' => rand(50, 200),
                    'conversion_rate' => round(rand(15, 85) + (rand(0, 99) / 100), 2),
                    'last_updated' => now()->subHours(rand(1, 48)),
                ]);

                $influencerCount++;
            }
        }

        // Ajouter l'utilisateur de test comme influenceur dans le pool français
        $frenchPool = InfluencerPool::where('language', 'français')->first();
        $testUser = User::where('email', 'test@rockpaperscissors.com')->first();

        if ($frenchPool && $testUser) {
            $testInfluencer = Influencer::create([
                'user_id' => $testUser->id,
                'pool_id' => $frenchPool->id,
                'is_eligible' => true,
                'has_claimed_reward' => false,
            ]);

            InfluencerStat::create([
                'influencer_id' => $testInfluencer->id,
                'referral_count' => 3200,
                'total_avax_spent' => 12.5,
                'active_referrals' => 85,
                'conversion_rate' => 68.5,
                'last_updated' => now()->subHours(2),
            ]);

            $influencerCount++;
        }

        $this->command->info("✅ {$influencerCount} influenceurs créés avec leurs statistiques");
    }

    private function getUserCount()
    {
        return User::count();
    }
}

