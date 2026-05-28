<?php
echo "🚀 Initialisation et Seeding de la base SQLite...\n";

try {
    $dbPath = __DIR__ . '/database/database.sqlite';
    
    // S'assurer que le fichier sqlite existe
    if (!file_exists($dbPath)) {
        touch($dbPath);
        echo "✅ Fichier database.sqlite créé\n";
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion SQLite réussie\n";

    // Nettoyer les données existantes (dans l'ordre des contraintes)
    echo "🧹 Nettoyage des données existantes...\n";
    
    $pdo->exec("DELETE FROM influencer_stats");
    $pdo->exec("DELETE FROM influencers");
    $pdo->exec("DELETE FROM influencer_pools");
    $pdo->exec("DELETE FROM referrals");
    $pdo->exec("DELETE FROM users");
    
    echo "  ✅ Anciennes données nettoyées\n";

    echo "🌱 Insertion des données de test...\n";

    // 1. Créer des utilisateurs de test
    echo "👥 Création des utilisateurs...\n";
    
    $users = [
        ['Test User', 'test@rockpaperscissors.com', 'REF-TEST01', '0x1111111111111111111111111111111111111111', 500.0],
        ['CryptoMaster', 'crypto@test.com', 'REF-USER01', '0x2222222222222222222222222222222222222222', 2500.0],
        ['BlockchainPro', 'blockchain@test.com', 'REF-USER02', '0x3333333333333333333333333333333333333333', 1800.0],
        ['Web3Guru', 'web3@test.com', 'REF-USER03', '0x4444444444444444444444444444444444444444', 3200.0],
        ['DeFiExpert', 'defi@test.com', 'REF-USER04', '0x5555555555555555555555555555555555555555', 1500.0],
        ['NFTCollector', 'nft@test.com', 'REF-USER05', '0x6666666666666666666666666666666666666666', 950.0]
    ];

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, referral_code, wallet_address, balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))');
    
    $userIds = [];
    foreach ($users as $user) {
        $stmt->execute([
            $user[0], // name
            $user[1], // email
            password_hash('password123', PASSWORD_DEFAULT), // password
            $user[2], // referral_code
            $user[3], // wallet_address
            $user[4]  // balance
        ]);
        $userIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($users) . " utilisateurs créés\n";

    // 2. Créer des parrainages
    echo "🤝 Création des parrainages...\n";
    
    // Créer des utilisateurs parrainés
    $referredUsers = [];
    for ($i = 0; $i < 50; $i++) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, datetime(\'now\'), datetime(\'now\'))');
        $stmt->execute([
            "Referred User " . ($i + 1),
            "referred_" . ($i + 1) . "@test.com",
            password_hash('password123', PASSWORD_DEFAULT)
        ]);
        $referredUsers[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($referredUsers) . " utilisateurs parrainés créés\n";

    // Créer les relations de parrainage
    $referralCounts = [15, 45, 28, 35, 19, 12];
    $validatedRates = [0.8, 0.84, 0.79, 0.89, 0.79, 0.75];
    
    $stmt = $pdo->prepare('INSERT INTO referrals (referrer_id, referred_id, status, created_at, updated_at) VALUES (?, ?, ?, datetime(\'now\', \'-5 days\'), datetime(\'now\'))');
    
    $totalReferrals = 0;
    $referredIndex = 0;
    
    for ($i = 0; $i < count($userIds); $i++) {
        $referrerId = $userIds[$i];
        $count = $referralCounts[$i];
        $validatedCount = intval($count * $validatedRates[$i]);
        
        for ($j = 0; $j < $count && $referredIndex < count($referredUsers); $j++) {
            $isValidated = $j < $validatedCount;
            $referredId = $referredUsers[$referredIndex];
            
            $stmt->execute([
                $referrerId,
                $referredId,
                $isValidated ? 'validated' : 'pending'
            ]);
            
            $totalReferrals++;
            $referredIndex++;
        }
    }
    echo "  ✅ $totalReferrals parrainages créés\n";

    // 3. Créer des pools d'influenceurs
    echo "🏆 Création des pools d'influenceurs...\n";
    
    $pools = [
        ['Influenceurs Français', 'fr', 5000, 30000, 10.0],
        ['English Influencers', 'en', 6000, 50000, 15.0],
        ['Influencers Españoles', 'es', 4000, 20000, 8.0]
    ];

    $stmt = $pdo->prepare('INSERT INTO influencer_pools (name, language, milestone, pool_milestone, reward_amount, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, datetime(\'now\'), datetime(\'now\'))');
    
    $poolIds = [];
    foreach ($pools as $pool) {
        $stmt->execute([
            $pool[0], // name
            $pool[1], // language
            $pool[2], // milestone
            $pool[3], // pool_milestone
            $pool[4]  // reward_amount
        ]);
        $poolIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($pools) . " pools d'influenceurs créés\n";

    // 4. Créer des influenceurs
    echo "🌟 Création des influenceurs...\n";
    
    $influencerData = [
        [1, 0, true, false],
        [2, 0, true, true],
        [3, 1, true, false],
        [4, 1, false, false],
        [5, 2, true, false]
    ];

    $stmt = $pdo->prepare('INSERT INTO influencers (user_id, pool_id, is_eligible, has_claimed, created_at, updated_at) VALUES (?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))');
    
    $influencerIds = [];
    foreach ($influencerData as $inf) {
        $stmt->execute([
            $userIds[$inf[0]], // user_id
            $poolIds[$inf[1]], // pool_id
            $inf[2] ? 1 : 0,
            $inf[3] ? 1 : 0
        ]);
        $influencerIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($influencerData) . " influenceurs créés\n";

    // 5. Créer des statistiques d'influenceurs
    echo "📊 Création des statistiques d'influenceurs...\n";
    
    $stmt = $pdo->prepare('INSERT INTO influencer_stats (influencer_id, referral_count, total_avax_spent, created_at, updated_at) VALUES (?, ?, ?, datetime(\'now\'), datetime(\'now\'))');
    
    $statsData = [
        [4200, 25.5],
        [3800, 18.2],
        [6200, 42.1],
        [4900, 31.7],
        [3200, 19.8]
    ];
    
    foreach ($influencerIds as $i => $infId) {
        $stmt->execute([
            $infId,
            $statsData[$i][0], // referral_count
            $statsData[$i][1]  // total_avax_spent
        ]);
    }
    echo "  ✅ " . count($influencerIds) . " statistiques d'influenceurs créées\n";

    echo "\n🎉 Base SQLite initialisée et peuplée avec succès !\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
