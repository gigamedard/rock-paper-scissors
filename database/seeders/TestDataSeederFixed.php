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
use Illuminate\Support\Facades\DB;

class TestDataSeederFixed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Création des données de test (version corrigée)...');

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

        // Vérifier les colonnes existantes dans la table users
        $userColumns = \Schema::getColumnListing('users');
        $this->command->info('Colonnes disponibles: ' . implode(', ', $userColumns));

        // Données de base pour tous les utilisateurs
        $baseUserData = [
            'name' => 'Test User',
            'email' => 'test@rockpaperscissors.com',
            'password' => Hash::make('password123'),
        ];

        // Ajouter les colonnes optionnelles si elles existent
        if (in_array('referral_code', $userColumns)) {
            $baseUserData['referral_code'] = 'REF-TEST01';
        }

        if (in_array('balance', $userColumns)) {
            $baseUserData['balance'] = 500;
        }

        if (in_array('wallet_address', $userColumns)) {
            $baseUserData['wallet_address'] = '0x1111111111111111111111111111111111111111';
        }

        // Créer l'utilisateur principal
        $mainUser = User::create($baseUserData);

        // Créer d'autres utilisateurs de test
        $testUsers = [
            [
                'name' => 'CryptoMaster',
                'email' => 'crypto@test.com',
                'referral_code' => 'REF-USER01',
                'wallet_address' => '0x2222222222222222222222222222222222222222',
                'balance' => 2500,
            ],
            [
                'name' => 'BlockchainPro',
                'email' => 'blockchain@test.com',
                'referral_code' => 'REF-USER02',
                'wallet_address' => '0x3333333333333333333333333333333333333333',
                'balance' => 1800,
            ],
            [
                'name' => 'Web3Guru',
                'email' => 'web3@test.com',
                'referral_code' => 'REF-USER03',
                'wallet_address' => '0x4444444444444444444444444444444444444444',
                'balance' => 3200,
            ],
            [
                'name' => 'DeFiExpert',
                'email' => 'defi@test.com',
                'referral_code' => 'REF-USER04',
                'wallet_address' => '0x5555555555555555555555555555555555555555',
                'balance' => 1500,
            ],
            [
                'name' => 'NFTCollector',
                'email' => 'nft@test.com',
                'referral_code' => 'REF-USER05',
                'wallet_address' => '0x6666666666666666666666666666666666666666',
                'balance' => 950,
            ]
        ];

        foreach ($testUsers as $userData) {
            $userToCreate = [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password123'),
            ];

            // Ajouter les colonnes optionnelles si elles existent
            if (in_array('referral_code', $userColumns)) {
                $userToCreate['referral_code'] = $userData['referral_code'];
            }

            if (in_array('balance', $userColumns)) {
                $userToCreate['balance'] = $userData['balance'];
            }

            if (in_array('wallet_address', $userColumns)) {
                $userToCreate['wallet_address'] = $userData['wallet_address'];
            }

            User::create($userToCreate);
        }

        $this->command->info('✅ ' . (count($testUsers) + 1) . ' utilisateurs créés');
    }

    private function createReferrals()
    {
        $this->command->info('🤝 Création des parrainages...');

        $users = User::all();
        $referralCount = 0;

        foreach ($users as $referrer) {
            // Créer entre 3 et 8 parrainages par utilisateur
            $referralNumber = rand(3, 8);
            
            for ($i = 0; $i < $referralNumber; $i++) {
                $isValidated = rand(1, 100) <= 80; // 80% de chance d'être validé
                
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_email' => 'referred_' . $referralCount . '@test.com',
                    'referral_code' => $referrer->referral_code ?? 'REF-' . strtoupper(Str::random(6)),
                    'status' => $isValidated ? 'validated' : 'pending',
                    'reward_amount' => $isValidated ? 100 : 0,
                    'validated_at' => $isValidated ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);
                
                $referralCount++;
            }
        }

        $this->command->info('✅ ' . $referralCount . ' parrainages créés');
    }

    private function createInfluencerPools()
    {
        $this->command->info('🏆 Création des pools d\'influenceurs...');

        $pools = [
            [
                'name' => 'Influenceurs Français',
                'language' => 'fr',
                'total_reward_pool' => 10.0,
                'current_participants' => 8,
                'max_participants' => 10,
                'target_referrals' => 30000,
                'current_referrals' => 24500,
                'start_date' => now()->subDays(15),
                'end_date' => now()->addDays(45),
                'status' => 'active'
            ],
            [
                'name' => 'English Influencers',
                'language' => 'en',
                'total_reward_pool' => 15.0,
                'current_participants' => 12,
                'max_participants' => 15,
                'target_referrals' => 50000,
                'current_referrals' => 32000,
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(50),
                'status' => 'active'
            ],
            [
                'name' => 'Influencers Españoles',
                'language' => 'es',
                'total_reward_pool' => 8.0,
                'current_participants' => 6,
                'max_participants' => 8,
                'target_referrals' => 20000,
                'current_referrals' => 12800,
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(55),
                'status' => 'active'
            ]
        ];

        foreach ($pools as $poolData) {
            InfluencerPool::create($poolData);
        }

        $this->command->info('✅ ' . count($pools) . ' pools d\'influenceurs créés');
    }

    private function createInfluencers()
    {
        $this->command->info('🌟 Création des influenceurs...');

        $users = User::all();
        $pools = InfluencerPool::all();
        $influencerCount = 0;

        foreach ($pools as $pool) {
            // Créer 4-6 influenceurs par pool
            $influencersInPool = rand(4, 6);
            
            for ($i = 0; $i < $influencersInPool && $influencerCount < $users->count(); $i++) {
                $user = $users[$influencerCount];
                
                // Créer l'influenceur
                $influencer = Influencer::create([
                    'user_id' => $user->id,
                    'pool_id' => $pool->id,
                    'personal_target' => rand(3000, 8000),
                    'current_referrals' => rand(1500, 6500),
                    'reward_percentage' => rand(5, 15) / 100, // 5% à 15%
                    'status' => 'active',
                    'joined_at' => now()->subDays(rand(1, 30))
                ]);

                // Créer les statistiques de l'influenceur
                InfluencerStat::create([
                    'influencer_id' => $influencer->id,
                    'total_referrals' => $influencer->current_referrals,
                    'validated_referrals' => intval($influencer->current_referrals * 0.8),
                    'pending_referrals' => intval($influencer->current_referrals * 0.2),
                    'total_rewards_earned' => rand(50, 500) / 100, // 0.5 à 5 AVAX
                    'last_reward_claim' => rand(0, 1) ? now()->subDays(rand(1, 15)) : null,
                    'performance_score' => rand(70, 95) / 100, // 70% à 95%
                ]);

                $influencerCount++;
            }
        }

        $this->command->info('✅ ' . $influencerCount . ' influenceurs créés avec leurs statistiques');
    }
}

