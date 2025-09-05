<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Connexion à la base de données SQLite
    $pdo = new PDO('sqlite:../../../database/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Requête pour obtenir le classement des parraineurs
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

    echo json_encode($leaderboard, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

