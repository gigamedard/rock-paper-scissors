<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

    // Vérifier si les tables existent
    $stmt = $pdo->query("SHOW TABLES LIKE 'referrals'");
    $referralsExists = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersExists = $stmt->rowCount() > 0;

    if (!$usersExists) {
        throw new Exception("Table users non trouvée");
    }

    // Vérifier si la colonne referral_code existe
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'referral_code'");
    $hasReferralCode = $stmt->rowCount() > 0;

    if ($referralsExists && $hasReferralCode) {
        // Requête complète avec parrainages
        $stmt = $pdo->query("
            SELECT 
                u.name,
                u.referral_code,
                COUNT(r.id) as referral_count,
                SUM(CASE WHEN r.status = 'validated' THEN r.reward_amount ELSE 0 END) as total_rewards,
                COUNT(CASE WHEN r.status = 'validated' THEN 1 END) as validated_referrals,
                COUNT(CASE WHEN r.status = 'pending' THEN 1 END) as pending_referrals
            FROM users u
            LEFT JOIN referrals r ON u.id = r.referrer_id
            WHERE u.referral_code IS NOT NULL
            GROUP BY u.id, u.name, u.referral_code
            ORDER BY validated_referrals DESC, total_rewards DESC
        ");
    } else if ($hasReferralCode) {
        // Requête simplifiée sans parrainages mais avec referral_code
        $stmt = $pdo->query("
            SELECT 
                u.name,
                u.referral_code,
                0 as referral_count,
                0 as total_rewards,
                0 as validated_referrals,
                0 as pending_referrals
            FROM users u
            WHERE u.referral_code IS NOT NULL
            ORDER BY u.name
        ");
    } else {
        // Requête basique sans referral_code
        $stmt = $pdo->query("
            SELECT 
                u.name,
                CONCAT('REF-', LPAD(u.id, 3, '0')) as referral_code,
                0 as referral_count,
                0 as total_rewards,
                0 as validated_referrals,
                0 as pending_referrals
            FROM users u
            WHERE u.email LIKE '%@test.com' OR u.email = 'test@rockpaperscissors.com'
            ORDER BY u.name
        ");
    }

    $leaderboard = [];
    $rank = 1;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $leaderboard[] = [
            'rank' => $rank,
            'name' => $row['name'],
            'referral_code' => $row['referral_code'],
            'referral_count' => (int)$row['referral_count'],
            'validated_referrals' => (int)$row['validated_referrals'],
            'pending_referrals' => (int)$row['pending_referrals'],
            'total_rewards' => (int)$row['total_rewards'],
            'wallet_address' => '0x' . substr(md5($row['name']), 0, 8) . '...' . substr(md5($row['name']), -8)
        ];
        $rank++;
    }

    // Si aucune donnée, retourner des données d'exemple
    if (empty($leaderboard)) {
        $leaderboard = [
            [
                'rank' => 1,
                'name' => 'CryptoMaster',
                'referral_code' => 'REF-USER01',
                'referral_count' => 45,
                'validated_referrals' => 38,
                'pending_referrals' => 7,
                'total_rewards' => 3800,
                'wallet_address' => '0xe5e0833c...a1a38a14'
            ],
            [
                'rank' => 2,
                'name' => 'Web3Guru',
                'referral_code' => 'REF-USER03',
                'referral_count' => 35,
                'validated_referrals' => 31,
                'pending_referrals' => 4,
                'total_rewards' => 3100,
                'wallet_address' => '0x0e3402f5...1cd93d27'
            ]
        ];
    }

    echo json_encode($leaderboard, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'debug' => [
            'referrals_table_exists' => $referralsExists ?? false,
            'users_table_exists' => $usersExists ?? false,
            'has_referral_code' => $hasReferralCode ?? false
        ]
    ], JSON_PRETTY_PRINT);
}

