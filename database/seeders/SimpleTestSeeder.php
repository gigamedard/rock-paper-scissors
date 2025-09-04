<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SimpleTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Création des données de test (version simple)...');

        // 1. Créer des utilisateurs avec seulement les colonnes de base
        $this->createSimpleUsers();
        
        // 2. Créer des parrainages
        $this->createReferrals();
        
        // 3. Créer des pools d'influenceurs
        $this->createInfluencerPools();

        $this->command->info('✅ Données de test créées avec succès !');
    }

    private function createSimpleUsers()
    {
        $this->command->info('👥 Création des utilisateurs de test...');

        // Utilisateurs de base avec seulement les colonnes essentielles
        $users = [
            [
                'name' => 'Test User',
                'email' => 'test@rockpaperscissors.com',
                'password' => Hash::make('password123'),
                'referral_code' => 'REF-TEST01',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'CryptoMaster',
                'email' => 'crypto@test.com',
                'password' => Hash::make('password123'),
                'referral_code' => 'REF-USER01',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'BlockchainPro',
                'email' => 'blockchain@test.com',
                'password' => Hash::make('password123'),
                'referral_code' => 'REF-USER02',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Web3Guru',
                'email' => 'web3@test.com',
                'password' => Hash::make('password123'),
                'referral_code' => 'REF-USER03',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'DeFiExpert',
                'email' => 'defi@test.com',
                'password' => Hash::make('password123'),
                'referral_code' => 'REF-USER04',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'NFTCollector',
                'email' => 'nft@test.com',
                'password' => Hash::make('password123'),
                'referral_code' => 'REF-USER05',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ];

        foreach ($users as $user) {
            try {
                DB::table('users')->insert($user);
            } catch (\Exception $e) {
                // Si referral_code n'existe pas, essayer sans
                unset($user['referral_code']);
                DB::table('users')->insert($user);
            }
        }

        $this->command->info('✅ ' . count($users) . ' utilisateurs créés');
    }

    private function createReferrals()
    {
        $this->command->info('🤝 Création des parrainages...');

        // Récupérer les IDs des utilisateurs créés
        $userIds = DB::table('users')->pluck('id')->toArray();
        $referralCount = 0;

        foreach ($userIds as $referrerId) {
            // Créer entre 3 et 8 parrainages par utilisateur
            $referralNumber = rand(3, 8);
            
            for ($i = 0; $i < $referralNumber; $i++) {
                $isValidated = rand(1, 100) <= 80; // 80% de chance d'être validé
                
                DB::table('referrals')->insert([
                    'referrer_id' => $referrerId,
                    'referred_email' => 'referred_' . $referralCount . '@test.com',
                    'referral_code' => 'REF-' . strtoupper(substr(md5($referrerId . $i), 0, 6)),
                    'status' => $isValidated ? 'validated' : 'pending',
                    'reward_amount' => $isValidated ? 100 : 0,
                    'validated_at' => $isValidated ? Carbon::now()->subDays(rand(1, 30)) : null,
                    'created_at' => Carbon::now()->subDays(rand(1, 60)),
                    'updated_at' => Carbon::now(),
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
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->addDays(45),
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'English Influencers',
                'language' => 'en',
                'total_reward_pool' => 15.0,
                'current_participants' => 12,
                'max_participants' => 15,
                'target_referrals' => 50000,
                'current_referrals' => 32000,
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(50),
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Influencers Españoles',
                'language' => 'es',
                'total_reward_pool' => 8.0,
                'current_participants' => 6,
                'max_participants' => 8,
                'target_referrals' => 20000,
                'current_referrals' => 12800,
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(55),
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ];

        foreach ($pools as $pool) {
            DB::table('influencer_pools')->insert($pool);
        }

        $this->command->info('✅ ' . count($pools) . ' pools d\'influenceurs créés');
    }
}

