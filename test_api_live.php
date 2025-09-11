<?php

echo "🚀 Test en temps réel des API Laravel...\n\n";

// Configuration
$baseUrl = 'http://localhost:8000';
$testRoutes = [
    '/api/referral/leaderboard' => 'Classement des parraineurs',
    '/api/whitelist' => 'Informations whitelist',
    '/api/influencer/pools' => 'Pools d\'influenceurs',
    '/api/escrow/stats' => 'Statistiques marketplace',
    '/api/escrow/trades' => 'Trades actifs'
];

echo "📊 === TEST DES API LARAVEL EN TEMPS RÉEL ===\n";
echo "URL de base: $baseUrl\n";
echo "Serveur Laravel doit être démarré avec: php artisan serve --host=0.0.0.0 --port=8000\n\n";

$successCount = 0;
$totalCount = count($testRoutes);

foreach ($testRoutes as $route => $description) {
    echo "🌐 Test: $description\n";
    echo "   URL: $baseUrl$route\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $route);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($error) {
        echo "   ❌ Erreur cURL: $error\n";
        echo "   💡 Vérifiez que le serveur Laravel est démarré\n";
    } else {
        echo "   📊 Code HTTP: $httpCode\n";
        echo "   📋 Content-Type: $contentType\n";
        
        if ($httpCode === 200) {
            echo "   ✅ SUCCÈS - Route accessible\n";
            $successCount++;
            
            // Analyser la réponse JSON
            $jsonData = json_decode($response, true);
            if ($jsonData !== null) {
                echo "   📄 Réponse JSON valide\n";
                
                // Afficher un aperçu des données
                if (is_array($jsonData)) {
                    $count = count($jsonData);
                    echo "   📊 Nombre d'éléments: $count\n";
                    
                    if ($count > 0 && isset($jsonData[0])) {
                        $firstItem = $jsonData[0];
                        if (is_array($firstItem)) {
                            echo "   🔑 Clés disponibles: " . implode(', ', array_keys($firstItem)) . "\n";
                        }
                    }
                } else {
                    echo "   📊 Type de réponse: " . gettype($jsonData) . "\n";
                    if (is_object($jsonData) || is_array($jsonData)) {
                        $keys = is_object($jsonData) ? array_keys(get_object_vars($jsonData)) : array_keys($jsonData);
                        echo "   🔑 Clés disponibles: " . implode(', ', $keys) . "\n";
                    }
                }
                
                // Afficher un extrait de la réponse
                $preview = substr($response, 0, 200);
                if (strlen($response) > 200) {
                    $preview .= '...';
                }
                echo "   📝 Aperçu: $preview\n";
                
            } else {
                echo "   ⚠️ Réponse non-JSON ou invalide\n";
                echo "   📝 Réponse brute: " . substr($response, 0, 100) . "\n";
            }
            
        } elseif ($httpCode === 404) {
            echo "   ❌ Route non trouvée (404)\n";
            echo "   💡 Vérifiez que la route est bien définie dans routes/api.php\n";
        } elseif ($httpCode === 500) {
            echo "   ❌ Erreur serveur (500)\n";
            echo "   💡 Vérifiez les logs Laravel: storage/logs/laravel.log\n";
            echo "   📝 Réponse: " . substr($response, 0, 200) . "\n";
        } else {
            echo "   ⚠️ Code HTTP inattendu: $httpCode\n";
            echo "   📝 Réponse: " . substr($response, 0, 200) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

// Résumé final
echo "🎯 === RÉSUMÉ FINAL ===\n";
echo "✅ Routes fonctionnelles: $successCount/$totalCount\n";

if ($successCount === $totalCount) {
    echo "🎉 PARFAIT ! Toutes les API fonctionnent correctement !\n";
    echo "\n📱 Vous pouvez maintenant tester vos pages HTML dynamiques:\n";
    echo "   - http://localhost:8000/referral_dynamic.html\n";
    echo "   - http://localhost:8000/influencer_dynamic.html\n";
    echo "   - http://localhost:8000/marketplace_dynamic.html\n";
    echo "   - http://localhost:8000/test_integration.html\n";
} elseif ($successCount > 0) {
    echo "⚠️ Certaines API fonctionnent, d'autres ont des problèmes\n";
    echo "💡 Vérifiez les erreurs ci-dessus pour les routes qui échouent\n";
} else {
    echo "❌ Aucune API ne fonctionne\n";
    echo "💡 Vérifications à faire:\n";
    echo "   1. Le serveur Laravel est-il démarré ? (php artisan serve)\n";
    echo "   2. Le port 8000 est-il libre ?\n";
    echo "   3. Y a-t-il des erreurs dans storage/logs/laravel.log ?\n";
}

echo "\n🔧 Commandes utiles:\n";
echo "   - Démarrer serveur: php artisan serve --host=0.0.0.0 --port=8000\n";
echo "   - Voir les logs: tail -f storage/logs/laravel.log\n";
echo "   - Lister les routes: php artisan route:list --path=api\n";
echo "   - Vider les caches: php artisan optimize:clear\n";

echo "\n✅ Test terminé !\n";
