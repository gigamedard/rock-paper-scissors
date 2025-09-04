<?php
/**
 * Script de Test API Simple
 * Test les endpoints API sans dépendances Laravel complexes
 */

echo "🧪 Test Simple des API - Rock Paper Scissors\n";
echo str_repeat('=', 50) . "\n";

// Configuration
$baseUrl = 'http://localhost:8000';
$testResults = [];

// Fonction pour tester une URL
function testUrl($url, $description) {
    global $testResults;
    
    echo "🔍 Test: $description\n";
    echo "   URL: $url\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET',
            'header' => 'Accept: application/json'
        ]
    ]);
    
    $startTime = microtime(true);
    $content = @file_get_contents($url, false, $context);
    $endTime = microtime(true);
    
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    if ($content !== false) {
        $httpCode = 200;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $httpCode = isset($matches[1]) ? (int)$matches[1] : 200;
        }
        
        $success = $httpCode >= 200 && $httpCode < 400;
        $contentLength = strlen($content);
        
        if ($success) {
            echo "   ✅ Succès (HTTP $httpCode) - {$contentLength} bytes - {$responseTime}ms\n";
            
            // Analyser le contenu JSON si possible
            if (strpos($url, '/api/') !== false) {
                $json = json_decode($content, true);
                if ($json !== null) {
                    if (is_array($json)) {
                        echo "   📊 Données JSON: " . count($json) . " éléments\n";
                    } else {
                        echo "   📊 Données JSON: objet avec " . count((array)$json) . " propriétés\n";
                    }
                } else {
                    echo "   ⚠️ Réponse non-JSON\n";
                }
            } else {
                // Analyser le HTML
                if (strpos($content, '<title>') !== false) {
                    preg_match('/<title>(.*?)<\/title>/i', $content, $matches);
                    $title = isset($matches[1]) ? trim($matches[1]) : 'Sans titre';
                    echo "   📄 Page HTML: $title\n";
                }
            }
        } else {
            echo "   ❌ Erreur HTTP $httpCode\n";
        }
        
        $testResults[] = [
            'url' => $url,
            'description' => $description,
            'success' => $success,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'content_length' => $contentLength
        ];
    } else {
        echo "   ❌ Impossible d'accéder à l'URL\n";
        $testResults[] = [
            'url' => $url,
            'description' => $description,
            'success' => false,
            'http_code' => 0,
            'response_time' => 0,
            'content_length' => 0
        ];
    }
    
    echo "\n";
}

// Tests des pages HTML
echo "🌐 TEST DES PAGES HTML\n";
echo str_repeat('-', 30) . "\n";

$htmlPages = [
    '/index.html' => 'Page d\'accueil des modules',
    '/referral.html' => 'Dashboard de parrainage',
    '/influencer.html' => 'Dashboard influenceur',
    '/marketplace.html' => 'Marketplace P2P',
    '/test.php' => 'Page de test technique'
];

foreach ($htmlPages as $path => $description) {
    testUrl($baseUrl . $path, $description);
}

// Tests des API
echo "🔌 TEST DES API ENDPOINTS\n";
echo str_repeat('-', 30) . "\n";

$apiEndpoints = [
    '/api/referral/leaderboard' => 'Classement des parraineurs',
    '/api/whitelist' => 'Informations whitelist',
    '/api/influencer/pools' => 'Pools d\'influenceurs',
    '/api/influencer/leaderboard' => 'Classement des influenceurs',
    '/api/escrow/stats' => 'Statistiques du marketplace',
    '/api/escrow/trades' => 'Trades actifs'
];

foreach ($apiEndpoints as $path => $description) {
    testUrl($baseUrl . $path, $description);
}

// Test de preuve whitelist avec adresse fictive
testUrl($baseUrl . '/api/whitelist/proof/0x0000000000000000000000000000000000000001', 'Preuve whitelist (test)');

// Résumé des résultats
echo "📊 RÉSUMÉ DES TESTS\n";
echo str_repeat('=', 50) . "\n";

$totalTests = count($testResults);
$successfulTests = array_filter($testResults, function($result) {
    return $result['success'];
});
$successCount = count($successfulTests);
$failureCount = $totalTests - $successCount;

echo "Total des tests: $totalTests\n";
echo "✅ Réussis: $successCount\n";
echo "❌ Échoués: $failureCount\n";

if ($totalTests > 0) {
    $successRate = round(($successCount / $totalTests) * 100, 1);
    echo "📈 Taux de réussite: $successRate%\n";
}

// Statistiques de performance
if (!empty($successfulTests)) {
    $avgResponseTime = array_sum(array_column($successfulTests, 'response_time')) / count($successfulTests);
    echo "⚡ Temps de réponse moyen: " . round($avgResponseTime, 2) . "ms\n";
}

echo "\n";

// Tests échoués
if ($failureCount > 0) {
    echo "❌ TESTS ÉCHOUÉS:\n";
    foreach ($testResults as $result) {
        if (!$result['success']) {
            echo "   • {$result['description']} (HTTP {$result['http_code']})\n";
        }
    }
    echo "\n";
}

// Recommandations
echo "💡 RECOMMANDATIONS:\n";
echo "• Assurez-vous que le serveur est démarré: php -S 0.0.0.0:8000 -t public\n";
echo "• Vérifiez que les fichiers HTML sont dans le dossier public/\n";
echo "• Pour les API, vérifiez les routes dans routes/api.php\n";
echo "• Consultez les logs du serveur en cas d'erreur\n";

if ($successRate >= 80) {
    echo "\n🎉 Excellent ! La plupart des fonctionnalités sont opérationnelles.\n";
} elseif ($successRate >= 60) {
    echo "\n⚠️ Bon début, mais quelques problèmes à résoudre.\n";
} else {
    echo "\n🔧 Des ajustements sont nécessaires pour améliorer la fonctionnalité.\n";
}

echo "\n✅ Test terminé !\n";
?>

