<?php

echo "🚀 Création des données de test - Version finale adaptée à votre structure...\n";

try {
    // Configuration MySQL
    $host = '127.0.0.1';
    $port = '3306';
    $database = 'rock_peper_scissors';
    $username = 'root';
    $password = '';

    // Connexion à MySQL
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion MySQL réussie\n";

    // Nettoyer les données existantes (dans l'ordre des contraintes)
    echo "🧹 Nettoyage des données de test existantes...\n";
    
    $pdo->exec("DELETE FROM influencer_stats WHERE influencer_id IN (SELECT id FROM influencers WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@test.com' OR email = 'test@rockpaperscissors.com'))");
    $pdo->exec("DELETE FROM influencers WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@test.com' OR email = 'test@rockpaperscissors.com')");
    $pdo->exec("DELETE FROM influencer_pools WHERE name LIKE '%Test%' OR name LIKE '%Influenceurs%' OR name LIKE '%Influencers%'");
    $pdo->exec("DELETE FROM referrals WHERE referrer_id IN (SELECT id FROM users WHERE email LIKE '%@test.com' OR email = 'test@rockpaperscissors.com')");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@test.com' OR email = 'test@rockpaperscissors.com'");
    
    echo "  ✅ Données de test précédentes supprimées\n";

    echo "🌱 Insertion des nouvelles données de test...\n";

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

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, referral_code, wallet_address, balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    
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

    // 2. Créer des parrainages (structure adaptée: referrer_id, referred_id, status)
    echo "🤝 Création des parrainages...\n";
    
    // Créer des utilisateurs "parrainés" fictifs pour avoir des referred_id
    $referredUsers = [];
    for ($i = 0; $i < 50; $i++) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            "Referred User " . ($i + 1),
            "referred_" . ($i + 1) . "@test.com",
            password_hash('password123', PASSWORD_DEFAULT)
        ]);
        $referredUsers[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($referredUsers) . " utilisateurs parrainés créés\n";

    // Créer les relations de parrainage
    $referralCounts = [15, 45, 28, 35, 19, 12]; // Nombre de parrainages par utilisateur
    $validatedRates = [0.8, 0.84, 0.79, 0.89, 0.79, 0.75]; // Taux de validation
    
    $stmt = $pdo->prepare('INSERT INTO referrals (referrer_id, referred_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW())');
    
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
                $isValidated ? 'validated' : 'pending',
                date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days'))
            ]);
            
            $totalReferrals++;
            $referredIndex++;
        }
    }
    echo "  ✅ $totalReferrals parrainages créés\n";

    // 3. Créer des pools d'influenceurs (structure adaptée)
    echo "🏆 Création des pools d'influenceurs...\n";
    
    $pools = [
        ['Influenceurs Français', 'fr', 5000, 30000, 10.0],
        ['English Influencers', 'en', 6000, 50000, 15.0],
        ['Influencers Españoles', 'es', 4000, 20000, 8.0]
    ];

    $stmt = $pdo->prepare('INSERT INTO influencer_pools (name, language, milestone, pool_milestone, reward_amount, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())');
    
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

    // 4. Créer des influenceurs (structure adaptée)
    echo "🌟 Création des influenceurs...\n";
    
    $influencerData = [
        [1, 0, true, false],  // CryptoMaster dans pool FR, éligible, pas encore réclamé
        [2, 0, true, true],   // BlockchainPro dans pool FR, éligible, déjà réclamé
        [3, 1, true, false],  // Web3Guru dans pool EN, éligible, pas encore réclamé
        [4, 1, false, false], // DeFiExpert dans pool EN, pas encore éligible
        [5, 2, true, false]   // NFTCollector dans pool ES, éligible, pas encore réclamé
    ];

    $stmt = $pdo->prepare('INSERT INTO influencers (user_id, pool_id, is_eligible, has_claimed, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
    
    $influencerIds = [];
    foreach ($influencerData as $inf) {
        $stmt->execute([
            $userIds[$inf[0]], // user_id
            $poolIds[$inf[1]], // pool_id
            $inf[2] ? 1 : 0,   // is_eligible
            $inf[3] ? 1 : 0    // has_claimed
        ]);
        $influencerIds[] = $pdo->lastInsertId();
    }
    echo "  ✅ " . count($influencerData) . " influenceurs créés\n";

    // 5. Créer des statistiques d'influenceurs (structure adaptée)
    echo "📊 Création des statistiques d'influenceurs...\n";
    
    $stmt = $pdo->prepare('INSERT INTO influencer_stats (influencer_id, referral_count, total_avax_spent, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    
    $statsData = [
        [4200, 25.5], // CryptoMaster
        [3800, 18.2], // BlockchainPro
        [6200, 42.1], // Web3Guru
        [4900, 31.7], // DeFiExpert
        [3200, 19.8]  // NFTCollector
    ];
    
    foreach ($influencerIds as $i => $infId) {
        $stmt->execute([
            $infId,
            $statsData[$i][0], // referral_count
            $statsData[$i][1]  // total_avax_spent
        ]);
    }
    echo "  ✅ " . count($influencerIds) . " statistiques d'influenceurs créées\n";

    echo "\n🎉 Données de test créées avec succès dans MySQL !\n";
    echo "\n📊 Résumé final :\n";
    echo "  - 6 utilisateurs principaux avec codes de parrainage\n";
    echo "  - 50 utilisateurs parrainés\n";
    echo "  - $totalReferrals relations de parrainage (80% validées en moyenne)\n";
    echo "  - " . count($pools) . " pools d'influenceurs actifs\n";
    echo "  - " . count($influencerData) . " influenceurs avec statistiques\n";
    echo "\n🚀 Vous pouvez maintenant tester les API !\n";
    echo "💡 Testez avec: php artisan serve puis /api/referral/leaderboard_mysql.php\n";

    // Afficher quelques statistiques pour vérification
    echo "\n📈 Vérification des données créées :\n";
    
    $stmt = $pdo->query("SELECT name, referral_code, balance FROM users WHERE referral_code IS NOT NULL ORDER BY balance DESC");
    $testUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($testUsers as $user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated FROM referrals WHERE referrer_id = (SELECT id FROM users WHERE referral_code = ?)");
        $stmt->execute([$user['referral_code']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "  👤 {$user['name']} ({$user['referral_code']}) - Balance: {$user['balance']} - Parrainages: {$stats['validated']}/{$stats['total']}\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
