<?php

echo "🚀 Création complète de la base de données et des données de test...\n";

try {
    // Supprimer l'ancienne base de données
    if (file_exists('database/database.sqlite')) {
        unlink('database/database.sqlite');
        echo "🗑️ Ancienne base supprimée\n";
    }

    // Créer une nouvelle base de données
    $pdo = new PDO('sqlite:database/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Nouvelle base de données créée\n";

    // Créer les tables nécessaires
    echo "📋 Création des tables...\n";

    // Table users (structure simplifiée)
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            referral_code VARCHAR(255) UNIQUE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )
    ");
    echo "  ✅ Table users créée\n";

    // Table referrals
    $pdo->exec("
        CREATE TABLE referrals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            referrer_id INTEGER NOT NULL,
            referred_email VARCHAR(255) NOT NULL,
            referral_code VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            reward_amount DECIMAL(10,2) DEFAULT 0,
            validated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (referrer_id) REFERENCES users(id)
        )
    ");
    echo "  ✅ Table referrals créée\n";

    // Table influencer_pools
    $pdo->exec("
        CREATE TABLE influencer_pools (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            language VARCHAR(10) NOT NULL,
            total_reward_pool DECIMAL(10,2) NOT NULL,
            current_participants INTEGER DEFAULT 0,
            max_participants INTEGER NOT NULL,
            target_referrals INTEGER NOT NULL,
            current_referrals INTEGER DEFAULT 0,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )
    ");
    echo "  ✅ Table influencer_pools créée\n";

    // Table influencers
    $pdo->exec("
        CREATE TABLE influencers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            pool_id INTEGER NOT NULL,
            personal_target INTEGER NOT NULL,
            current_referrals INTEGER DEFAULT 0,
            reward_percentage DECIMAL(5,4) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            joined_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (pool_id) REFERENCES influencer_pools(id)
        )
    ");
    echo "  ✅ Table influencers créée\n";

    // Table influencer_stats
    $pdo->exec("
        CREATE TABLE influencer_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            influencer_id INTEGER NOT NULL,
            total_referrals INTEGER DEFAULT 0,
            validated_referrals INTEGER DEFAULT 0,
            pending_referrals INTEGER DEFAULT 0,
            total_rewards_earned DECIMAL(10,4) DEFAULT 0,
            last_reward_claim DATETIME NULL,
            performance_score DECIMAL(3,2) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (influencer_id) REFERENCES influencers(id)
        )
    ");
    echo "  ✅ Table influencer_stats créée\n";

    echo "🌱 Insertion des données de test...\n";

    // Insérer des utilisateurs
    $users = [
        ['Test User', 'test@rockpaperscissors.com', 'REF-TEST01'],
        ['CryptoMaster', 'crypto@test.com', 'REF-USER01'],
        ['BlockchainPro', 'blockchain@test.com', 'REF-USER02'],
        ['Web3Guru', 'web3@test.com', 'REF-USER03'],
        ['DeFiExpert', 'defi@test.com', 'REF-USER04'],
        ['NFTCollector', 'nft@test.com', 'REF-USER05']
    ];

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, referral_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $userIds = [];
    
    foreach ($users as $user) {
        $stmt->execute([
            $user[0],
            $user[1],
            password_hash('password123', PASSWORD_DEFAULT),
            $user[2],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        $userIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($users) . " utilisateurs créés\n";

    // Insérer des parrainages
    $referralCounts = [15, 45, 28, 35, 19, 12]; // Nombre de parrainages par utilisateur
    $validatedRates = [0.8, 0.84, 0.79, 0.89, 0.79, 0.75]; // Taux de validation
    
    $stmt = $pdo->prepare('INSERT INTO referrals (referrer_id, referred_email, referral_code, status, reward_amount, validated_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    
    $totalReferrals = 0;
    for ($i = 0; $i < count($userIds); $i++) {
        $userId = $userIds[$i];
        $referralCode = $users[$i][2];
        $count = $referralCounts[$i];
        $validatedCount = intval($count * $validatedRates[$i]);
        
        for ($j = 0; $j < $count; $j++) {
            $isValidated = $j < $validatedCount;
            $stmt->execute([
                $userId,
                "referred_{$totalReferrals}@test.com",
                $referralCode,
                $isValidated ? 'validated' : 'pending',
                $isValidated ? 100 : 0,
                $isValidated ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')) : null,
                date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')),
                date('Y-m-d H:i:s')
            ]);
            $totalReferrals++;
        }
    }
    echo "  ✅ $totalReferrals parrainages créés\n";

    // Insérer des pools d'influenceurs
    $pools = [
        ['Influenceurs Français', 'fr', 10.0, 8, 10, 30000, 24500],
        ['English Influencers', 'en', 15.0, 12, 15, 50000, 32000],
        ['Influencers Españoles', 'es', 8.0, 6, 8, 20000, 12800]
    ];

    $stmt = $pdo->prepare('INSERT INTO influencer_pools (name, language, total_reward_pool, current_participants, max_participants, target_referrals, current_referrals, start_date, end_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $poolIds = [];
    foreach ($pools as $pool) {
        $stmt->execute([
            $pool[0], $pool[1], $pool[2], $pool[3], $pool[4], $pool[5], $pool[6],
            date('Y-m-d H:i:s', strtotime('-15 days')),
            date('Y-m-d H:i:s', strtotime('+45 days')),
            'active',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        $poolIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($pools) . " pools d'influenceurs créés\n";

    // Insérer des influenceurs
    $influencerData = [
        [1, 0, 5000, 4200, 0.10], // CryptoMaster dans pool FR
        [2, 0, 4500, 3800, 0.08], // BlockchainPro dans pool FR
        [3, 1, 6000, 6200, 0.12], // Web3Guru dans pool EN
        [4, 1, 5500, 4900, 0.09], // DeFiExpert dans pool EN
        [5, 2, 4000, 3200, 0.11]  // NFTCollector dans pool ES
    ];

    $stmt = $pdo->prepare('INSERT INTO influencers (user_id, pool_id, personal_target, current_referrals, reward_percentage, status, joined_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    $influencerIds = [];
    foreach ($influencerData as $inf) {
        $stmt->execute([
            $userIds[$inf[0]], $poolIds[$inf[1]], $inf[2], $inf[3], $inf[4],
            'active',
            date('Y-m-d H:i:s', strtotime('-' . rand(5, 20) . ' days')),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        $influencerIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($influencerData) . " influenceurs créés\n";

    // Insérer des statistiques d'influenceurs
    $stmt = $pdo->prepare('INSERT INTO influencer_stats (influencer_id, total_referrals, validated_referrals, pending_referrals, total_rewards_earned, last_reward_claim, performance_score, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    foreach ($influencerIds as $i => $infId) {
        $totalRefs = $influencerData[$i][3];
        $validatedRefs = intval($totalRefs * 0.8);
        $pendingRefs = $totalRefs - $validatedRefs;
        
        $stmt->execute([
            $infId, $totalRefs, $validatedRefs, $pendingRefs,
            rand(100, 500) / 100, // 1.00 à 5.00 AVAX
            date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' days')),
            rand(75, 95) / 100, // 0.75 à 0.95
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
    }
    echo "  ✅ " . count($influencerIds) . " statistiques d'influenceurs créées\n";

    echo "\n🎉 Base de données et données de test créées avec succès !\n";
    echo "\n📊 Résumé :\n";
    echo "  - 6 utilisateurs avec codes de parrainage\n";
    echo "  - $totalReferrals parrainages (80% validés en moyenne)\n";
    echo "  - 3 pools d'influenceurs actifs\n";
    echo "  - 5 influenceurs avec statistiques\n";
    echo "\n🚀 Vous pouvez maintenant tester les API !\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

