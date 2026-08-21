<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class QuickTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Création rapide des données de test...');

        // Nettoyer les tables existantes
        DB::table('influencer_stats')->truncate();
        DB::table('influencers')->truncate();
        DB::table('influencer_pools')->truncate();
        DB::table('referrals')->truncate();

        // 1. Créer des utilisateurs avec codes de parrainage
        $users = [
            ['name' => 'Test User', 'email' => 'test@rockpaperscissors.com', 'referral_code' => 'REF-TEST01'],
            ['name' => 'CryptoMaster', 'email' => 'crypto@test.com', 'referral_code' => 'REF-USER01'],
            ['name' => 'BlockchainPro', 'email' => 'blockchain@test.com', 'referral_code' => 'REF-USER02'],
            ['name' => 'Web3Guru', 'email' => 'web3@test.com', 'referral_code' => 'REF-USER03'],
            ['name' => 'DeFiExpert', 'email' => 'defi@test.com', 'referral_code' => 'REF-USER04'],
            ['name' => 'NFTCollector', 'email' => 'nft@test.com', 'referral_code' => 'REF-USER05']
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make('password123'),
                    'referral_code' => $user['referral_code'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        // 2. Récupérer les IDs des utilisateurs
        $userIds = DB::table('users')->whereIn('email', array_column($users, 'email'))->pluck('id', 'email');

        // 3. Créer des parrainages
        $referrals = [
            ['referrer_email' => 'test@rockpaperscissors.com', 'count' => 15, 'validated' => 12],
            ['referrer_email' => 'crypto@test.com', 'count' => 45, 'validated' => 38],
            ['referrer_email' => 'blockchain@test.com', 'count' => 28, 'validated' => 22],
            ['referrer_email' => 'web3@test.com', 'count' => 35, 'validated' => 31],
            ['referrer_email' => 'defi@test.com', 'count' => 19, 'validated' => 15],
            ['referrer_email' => 'nft@test.com', 'count' => 12, 'validated' => 9]
        ];

        $referralId = 1;
        foreach ($referrals as $ref) {
            $referrerId = $userIds[$ref['referrer_email']];
            $referralCode = DB::table('users')->where('id', $referrerId)->value('referral_code');
            
            for ($i = 0; $i < $ref['count']; $i++) {
                $isValidated = $i < $ref['validated'];
                
                DB::table('referrals')->insert([
                    'referrer_id' => $referrerId,
                    'referred_email' => "referred_{$referralId}@test.com",
                    'referral_code' => $referralCode,
                    'status' => $isValidated ? 'validated' : 'pending',
                    'reward_amount' => $isValidated ? 100 : 0,
                    'validated_at' => $isValidated ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now()
                ]);
                
                $referralId++;
            }
        }

        // 4. Créer des pools d'influenceurs
        DB::table('influencer_pools')->insert([
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
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
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
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
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
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $this->command->info('✅ Données de test créées avec succès !');
        $this->command->info('📊 Résumé :');
        $this->command->info('   - 6 utilisateurs avec codes de parrainage');
        $this->command->info('   - 154 parrainages (127 validés, 27 en attente)');
        $this->command->info('   - 3 pools d\'influenceurs actifs');
    }
}

