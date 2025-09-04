-- Script SQL pour créer les données de test
-- Exécuter avec : php artisan db:seed --class=DatabaseSeeder ou directement en SQL

-- 1. Insérer des utilisateurs de test
INSERT INTO users (name, email, password, referral_code, created_at, updated_at) VALUES
('Test User', 'test@rockpaperscissors.com', '$2y$12$D6Gr.61.fXOw32FSvdYf7.q/URh5pCCkqvveRc74PdMu8CShV6lQi', 'REF-TEST01', NOW(), NOW()),
('CryptoMaster', 'crypto@test.com', '$2y$12$D6Gr.61.fXOw32FSvdYf7.q/URh5pCCkqvveRc74PdMu8CShV6lQi', 'REF-USER01', NOW(), NOW()),
('BlockchainPro', 'blockchain@test.com', '$2y$12$D6Gr.61.fXOw32FSvdYf7.q/URh5pCCkqvveRc74PdMu8CShV6lQi', 'REF-USER02', NOW(), NOW()),
('Web3Guru', 'web3@test.com', '$2y$12$D6Gr.61.fXOw32FSvdYf7.q/URh5pCCkqvveRc74PdMu8CShV6lQi', 'REF-USER03', NOW(), NOW()),
('DeFiExpert', 'defi@test.com', '$2y$12$D6Gr.61.fXOw32FSvdYf7.q/URh5pCCkqvveRc74PdMu8CShV6lQi', 'REF-USER04', NOW(), NOW()),
('NFTCollector', 'nft@test.com', '$2y$12$D6Gr.61.fXOw32FSvdYf7.q/URh5pCCkqvveRc74PdMu8CShV6lQi', 'REF-USER05', NOW(), NOW());

-- 2. Insérer des parrainages (en supposant que les IDs des utilisateurs sont 1-6)
INSERT INTO referrals (referrer_id, referred_email, referral_code, status, reward_amount, validated_at, created_at, updated_at) VALUES
-- Parrainages pour Test User (ID: 1)
(1, 'referred_1@test.com', 'REF-TEST01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), NOW()),
(1, 'referred_2@test.com', 'REF-TEST01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), NOW()),
(1, 'referred_3@test.com', 'REF-TEST01', 'pending', 0, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), NOW()),
(1, 'referred_4@test.com', 'REF-TEST01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), NOW()),
(1, 'referred_5@test.com', 'REF-TEST01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),

-- Parrainages pour CryptoMaster (ID: 2)
(2, 'referred_6@test.com', 'REF-USER01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), NOW()),
(2, 'referred_7@test.com', 'REF-USER01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), NOW()),
(2, 'referred_8@test.com', 'REF-USER01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), NOW()),
(2, 'referred_9@test.com', 'REF-USER01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 13 DAY), NOW()),
(2, 'referred_10@test.com', 'REF-USER01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), NOW()),
(2, 'referred_11@test.com', 'REF-USER01', 'validated', 100, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY), NOW()),
(2, 'referred_12@test.com', 'REF-USER01', 'pending', 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),

-- Parrainages pour BlockchainPro (ID: 3)
(3, 'referred_13@test.com', 'REF-USER02', 'validated', 100, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), NOW()),
(3, 'referred_14@test.com', 'REF-USER02', 'validated', 100, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), NOW()),
(3, 'referred_15@test.com', 'REF-USER02', 'validated', 100, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),
(3, 'referred_16@test.com', 'REF-USER02', 'pending', 0, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), NOW()),
(3, 'referred_17@test.com', 'REF-USER02', 'validated', 100, DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), NOW()),

-- Parrainages pour Web3Guru (ID: 4)
(4, 'referred_18@test.com', 'REF-USER03', 'validated', 100, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), NOW()),
(4, 'referred_19@test.com', 'REF-USER03', 'validated', 100, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), NOW()),
(4, 'referred_20@test.com', 'REF-USER03', 'validated', 100, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), NOW()),
(4, 'referred_21@test.com', 'REF-USER03', 'validated', 100, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),
(4, 'referred_22@test.com', 'REF-USER03', 'validated', 100, DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), NOW()),
(4, 'referred_23@test.com', 'REF-USER03', 'validated', 100, DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 16 DAY), NOW()),

-- Parrainages pour DeFiExpert (ID: 5)
(5, 'referred_24@test.com', 'REF-USER04', 'validated', 100, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), NOW()),
(5, 'referred_25@test.com', 'REF-USER04', 'validated', 100, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), NOW()),
(5, 'referred_26@test.com', 'REF-USER04', 'pending', 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(5, 'referred_27@test.com', 'REF-USER04', 'validated', 100, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), NOW()),

-- Parrainages pour NFTCollector (ID: 6)
(6, 'referred_28@test.com', 'REF-USER05', 'validated', 100, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), NOW()),
(6, 'referred_29@test.com', 'REF-USER05', 'validated', 100, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), NOW()),
(6, 'referred_30@test.com', 'REF-USER05', 'pending', 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW());

-- 3. Insérer des pools d'influenceurs
INSERT INTO influencer_pools (name, language, total_reward_pool, current_participants, max_participants, target_referrals, current_referrals, start_date, end_date, status, created_at, updated_at) VALUES
('Influenceurs Français', 'fr', 10.0, 8, 10, 30000, 24500, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_ADD(NOW(), INTERVAL 45 DAY), 'active', NOW(), NOW()),
('English Influencers', 'en', 15.0, 12, 15, 50000, 32000, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 50 DAY), 'active', NOW(), NOW()),
('Influencers Españoles', 'es', 8.0, 6, 8, 20000, 12800, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 55 DAY), 'active', NOW(), NOW());

-- 4. Insérer des influenceurs (en supposant que les IDs des pools sont 1-3)
INSERT INTO influencers (user_id, pool_id, personal_target, current_referrals, reward_percentage, status, joined_at, created_at, updated_at) VALUES
(2, 1, 5000, 4200, 0.10, 'active', DATE_SUB(NOW(), INTERVAL 10 DAY), NOW(), NOW()),
(3, 1, 4500, 3800, 0.08, 'active', DATE_SUB(NOW(), INTERVAL 8 DAY), NOW(), NOW()),
(4, 2, 6000, 6200, 0.12, 'active', DATE_SUB(NOW(), INTERVAL 12 DAY), NOW(), NOW()),
(5, 2, 5500, 4900, 0.09, 'active', DATE_SUB(NOW(), INTERVAL 6 DAY), NOW(), NOW()),
(6, 3, 4000, 3200, 0.11, 'active', DATE_SUB(NOW(), INTERVAL 4 DAY), NOW(), NOW());

-- 5. Insérer des statistiques d'influenceurs (en supposant que les IDs des influenceurs sont 1-5)
INSERT INTO influencer_stats (influencer_id, total_referrals, validated_referrals, pending_referrals, total_rewards_earned, last_reward_claim, performance_score, created_at, updated_at) VALUES
(1, 4200, 3360, 840, 2.5, DATE_SUB(NOW(), INTERVAL 3 DAY), 0.85, NOW(), NOW()),
(2, 3800, 3040, 760, 1.8, DATE_SUB(NOW(), INTERVAL 5 DAY), 0.82, NOW(), NOW()),
(3, 6200, 4960, 1240, 4.2, DATE_SUB(NOW(), INTERVAL 2 DAY), 0.92, NOW(), NOW()),
(4, 4900, 3920, 980, 3.1, DATE_SUB(NOW(), INTERVAL 4 DAY), 0.88, NOW(), NOW()),
(5, 3200, 2560, 640, 1.9, DATE_SUB(NOW(), INTERVAL 6 DAY), 0.79, NOW(), NOW());

