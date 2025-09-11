<?php

echo "🔍 Inspection de la structure de votre base de données...\n";

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
    echo "✅ Connexion MySQL réussie\n\n";

    // Tables importantes à examiner
    $importantTables = ['users', 'referrals', 'influencer_pools', 'influencers', 'influencer_stats'];
    
    foreach ($importantTables as $table) {
        echo "📋 === STRUCTURE DE LA TABLE '$table' ===\n";
        
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($columns)) {
                echo "❌ Table '$table' non trouvée ou vide\n\n";
                continue;
            }
            
            echo "Colonnes disponibles:\n";
            foreach ($columns as $column) {
                $nullable = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
                $extra = $column['Extra'] ? "({$column['Extra']})" : '';
                
                echo "  - {$column['Field']} : {$column['Type']} $nullable $default $extra\n";
            }
            
            // Vérifier s'il y a des données existantes
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "📊 Nombre d'enregistrements existants: $count\n";
            
            // Pour la table referrals, montrer quelques exemples si elle existe
            if ($table === 'referrals' && $count > 0) {
                echo "📝 Exemples d'enregistrements (5 premiers):\n";
                $stmt = $pdo->query("SELECT * FROM referrals LIMIT 5");
                $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($examples as $i => $example) {
                    echo "  Enregistrement " . ($i + 1) . ":\n";
                    foreach ($example as $field => $value) {
                        $displayValue = $value === null ? 'NULL' : "'$value'";
                        echo "    $field: $displayValue\n";
                    }
                    echo "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Erreur lors de l'inspection de '$table': " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
    
    // Suggestions basées sur la structure trouvée
    echo "💡 === SUGGESTIONS POUR CRÉER LES DONNÉES ===\n";
    
    // Vérifier la table users
    try {
        $stmt = $pdo->query("DESCRIBE users");
        $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "🔧 Pour la table users, nous pouvons utiliser:\n";
        $usableColumns = array_intersect($userColumns, ['name', 'email', 'password', 'referral_code', 'balance', 'wallet_address', 'created_at', 'updated_at']);
        foreach ($usableColumns as $col) {
            echo "  ✅ $col\n";
        }
        
        $missingColumns = array_diff(['name', 'email', 'password', 'referral_code'], $userColumns);
        if (!empty($missingColumns)) {
            echo "  ⚠️ Colonnes manquantes: " . implode(', ', $missingColumns) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Impossible d'analyser la table users\n";
    }
    
    // Vérifier la table referrals
    try {
        $stmt = $pdo->query("DESCRIBE referrals");
        $referralColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\n🔧 Pour la table referrals, nous pouvons utiliser:\n";
        $usableColumns = array_intersect($referralColumns, ['referrer_id', 'referred_user_id', 'referred_email', 'referral_code', 'status', 'reward_amount', 'validated_at', 'created_at', 'updated_at']);
        foreach ($usableColumns as $col) {
            echo "  ✅ $col\n";
        }
        
        echo "\n🔧 Requête suggérée pour referrals:\n";
        $insertColumns = array_intersect($referralColumns, ['referrer_id', 'status', 'created_at', 'updated_at']);
        echo "  INSERT INTO referrals (" . implode(', ', $insertColumns) . ") VALUES (...)\n";
        
    } catch (Exception $e) {
        echo "❌ Impossible d'analyser la table referrals\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "\n";
}
