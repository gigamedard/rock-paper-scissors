<?php

try {
    $pdo = new PDO('sqlite:database/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🌱 Test d'insertion directe en base...\n";

    // Vérifier les colonnes de la table users
    $stmt = $pdo->query('PRAGMA table_info(users)');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "📋 Colonnes de la table users:\n";
    $columnNames = [];
    foreach ($columns as $col) {
        echo "  - " . $col['name'] . " (" . $col['type'] . ")\n";
        $columnNames[] = $col['name'];
    }

    // Test d'insertion d'un utilisateur
    $hasReferralCode = in_array('referral_code', $columnNames);
    
    if ($hasReferralCode) {
        $sql = 'INSERT OR REPLACE INTO users (name, email, password, referral_code, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)';
        $params = [
            'Test User Direct',
            'direct@test.com',
            password_hash('password123', PASSWORD_DEFAULT),
            'REF-DIRECT',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ];
    } else {
        $sql = 'INSERT OR REPLACE INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)';
        $params = [
            'Test User Direct',
            'direct@test.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ];
    }
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        echo "✅ Insertion utilisateur réussie\n";
        $userId = $pdo->lastInsertId();
        echo "ID utilisateur: $userId\n";
        
        // Test d'insertion d'un parrainage
        if ($hasReferralCode) {
            $stmt = $pdo->prepare('INSERT INTO referrals (referrer_id, referred_email, referral_code, status, reward_amount, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $result = $stmt->execute([
                $userId,
                'referred_test@test.com',
                'REF-DIRECT',
                'validated',
                100,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                echo "✅ Insertion parrainage réussie\n";
            }
        }
        
        // Test d'insertion d'un pool d'influenceurs
        $stmt = $pdo->prepare('INSERT INTO influencer_pools (name, language, total_reward_pool, current_participants, max_participants, target_referrals, current_referrals, start_date, end_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $result = $stmt->execute([
            'Pool Test',
            'fr',
            5.0,
            1,
            5,
            1000,
            500,
            date('Y-m-d H:i:s', strtotime('-10 days')),
            date('Y-m-d H:i:s', strtotime('+30 days')),
            'active',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            echo "✅ Insertion pool d'influenceurs réussie\n";
        }
    }

    echo "✅ Test terminé avec succès\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

