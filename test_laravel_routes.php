<?php

echo "🔧 Test des routes Laravel et résolution des problèmes...\n\n";

// 1. Tester les commandes Laravel
echo "📋 === TEST DES COMMANDES LARAVEL ===\n";

$commands = [
    'php artisan route:list --path=api' => 'Lister les routes API',
    'php artisan config:clear' => 'Vider le cache de configuration',
    'php artisan route:clear' => 'Vider le cache des routes',
    'php artisan cache:clear' => 'Vider tous les caches'
];

foreach ($commands as $command => $description) {
    echo "🔧 $description...\n";
    echo "Commande: $command\n";
    
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ Succès\n";
        if (strpos($command, 'route:list') !== false) {
            echo "Routes trouvées:\n";
            foreach (array_slice($output, 0, 20) as $line) {
                if (strpos($line, 'api/') !== false || strpos($line, 'referral') !== false) {
                    echo "  $line\n";
                }
            }
        }
    } else {
        echo "❌ Erreur (code: $returnCode)\n";
        foreach (array_slice($output, 0, 5) as $line) {
            echo "  $line\n";
        }
    }
    echo "\n";
}

// 2. Tester l'accès direct aux routes
echo "📋 === TEST D'ACCÈS DIRECT AUX ROUTES ===\n";

$testRoutes = [
    'http://localhost:8000/api/referral/leaderboard',
    'http://localhost:8000/api/whitelist',
    'http://localhost:8000/api/influencer/pools',
    'http://localhost:8000/api/escrow/stats'
];

foreach ($testRoutes as $route) {
    echo "🌐 Test de: $route\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $route);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Erreur cURL: $error\n";
    } else {
        echo "📊 Code HTTP: $httpCode\n";
        if ($httpCode === 200) {
            echo "✅ Route accessible\n";
        } elseif ($httpCode === 404) {
            echo "❌ Route non trouvée (404)\n";
        } elseif ($httpCode === 500) {
            echo "❌ Erreur serveur (500)\n";
        } else {
            echo "⚠️ Code inattendu: $httpCode\n";
        }
        
        // Afficher les premières lignes de la réponse
        $lines = explode("\n", $response);
        foreach (array_slice($lines, 0, 3) as $line) {
            if (trim($line)) {
                echo "  " . trim($line) . "\n";
            }
        }
    }
    echo "\n";
}

// 3. Vérifier le contenu du fichier routes/api.php
echo "📋 === CONTENU DU FICHIER ROUTES/API.PHP ===\n";

if (file_exists('routes/api.php')) {
    $content = file_get_contents('routes/api.php');
    echo "Taille du fichier: " . strlen($content) . " caractères\n";
    
    // Chercher les routes spécifiques
    $routePatterns = [
        'referral' => '/Route::.*referral/i',
        'influencer' => '/Route::.*influencer/i',
        'escrow' => '/Route::.*escrow/i',
        'whitelist' => '/Route::.*whitelist/i'
    ];
    
    foreach ($routePatterns as $name => $pattern) {
        if (preg_match($pattern, $content)) {
            echo "✅ Routes '$name' trouvées\n";
        } else {
            echo "❌ Routes '$name' non trouvées\n";
        }
    }
    
    // Afficher les lignes contenant 'Route::'
    echo "\nDéfinitions de routes trouvées:\n";
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, 'Route::') !== false) {
            echo "  Ligne " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
} else {
    echo "❌ Fichier routes/api.php non trouvé\n";
}

echo "\n";

// 4. Vérifier les contrôleurs
echo "📋 === VÉRIFICATION DES CONTRÔLEURS ===\n";

$controllers = [
    'app/Http/Controllers/ReferralController.php',
    'app/Http/Controllers/InfluencerController.php',
    'app/Http/Controllers/EscrowController.php'
];

foreach ($controllers as $controller) {
    echo "🎮 " . basename($controller) . ":\n";
    
    if (file_exists($controller)) {
        $content = file_get_contents($controller);
        
        // Vérifier les méthodes
        $methods = ['index', 'leaderboard', 'pools', 'stats'];
        foreach ($methods as $method) {
            if (strpos($content, "function $method") !== false || strpos($content, "public function $method") !== false) {
                echo "  ✅ Méthode '$method' trouvée\n";
            }
        }
        
        // Vérifier la classe
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            echo "  ✅ Classe: " . $matches[1] . "\n";
        }
        
        // Vérifier le namespace
        if (preg_match('/namespace\s+([\w\\\\]+)/', $content, $matches)) {
            echo "  ✅ Namespace: " . $matches[1] . "\n";
        }
    } else {
        echo "  ❌ Fichier non trouvé\n";
    }
    echo "\n";
}

// 5. Suggestions de résolution
echo "💡 === SUGGESTIONS DE RÉSOLUTION ===\n";

echo "Si les routes ne fonctionnent toujours pas, essayez:\n";
echo "1. Redémarrer le serveur Laravel:\n";
echo "   php artisan serve --host=0.0.0.0 --port=8000\n\n";

echo "2. Vérifier les logs Laravel:\n";
echo "   tail -f storage/logs/laravel.log\n\n";

echo "3. Tester avec une route simple:\n";
echo "   curl -v http://localhost:8000/api/user\n\n";

echo "4. Vérifier la configuration Apache/Nginx si applicable\n\n";

echo "5. Forcer la régénération des caches:\n";
echo "   php artisan optimize:clear\n";
echo "   php artisan config:cache\n";
echo "   php artisan route:cache\n\n";

echo "✅ Diagnostic terminé !\n";
