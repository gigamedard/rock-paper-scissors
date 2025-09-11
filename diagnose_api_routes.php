<?php

echo "🔍 Diagnostic des routes API Laravel...\n\n";

try {
    // 1. Vérifier les fichiers de routes
    echo "📋 === VÉRIFICATION DES FICHIERS DE ROUTES ===\n";
    
    $routeFiles = [
        'routes/api.php' => 'Routes API',
        'routes/web.php' => 'Routes Web',
        'app/Providers/RouteServiceProvider.php' => 'Service Provider des routes'
    ];
    
    foreach ($routeFiles as $file => $description) {
        if (file_exists($file)) {
            echo "✅ $description ($file) - Existe\n";
            $size = filesize($file);
            echo "   Taille: $size bytes\n";
            
            if ($file === 'routes/api.php') {
                echo "   Contenu (premières lignes):\n";
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                foreach (array_slice($lines, 0, 10) as $i => $line) {
                    echo "   " . ($i + 1) . ": " . trim($line) . "\n";
                }
            }
        } else {
            echo "❌ $description ($file) - Manquant\n";
        }
    }
    
    echo "\n";
    
    // 2. Vérifier les contrôleurs API
    echo "📋 === VÉRIFICATION DES CONTRÔLEURS API ===\n";
    
    $controllers = [
        'app/Http/Controllers/ReferralController.php',
        'app/Http/Controllers/InfluencerController.php',
        'app/Http/Controllers/EscrowController.php'
    ];
    
    foreach ($controllers as $controller) {
        if (file_exists($controller)) {
            echo "✅ " . basename($controller) . " - Existe\n";
        } else {
            echo "❌ " . basename($controller) . " - Manquant\n";
        }
    }
    
    echo "\n";
    
    // 3. Vérifier la configuration Laravel
    echo "📋 === VÉRIFICATION DE LA CONFIGURATION LARAVEL ===\n";
    
    $configFiles = [
        '.env' => 'Configuration environnement',
        'config/app.php' => 'Configuration application',
        'bootstrap/app.php' => 'Bootstrap application'
    ];
    
    foreach ($configFiles as $file => $description) {
        if (file_exists($file)) {
            echo "✅ $description ($file) - Existe\n";
            
            if ($file === '.env') {
                echo "   Variables importantes:\n";
                $envContent = file_get_contents($file);
                $envLines = explode("\n", $envContent);
                foreach ($envLines as $line) {
                    if (strpos($line, 'APP_') === 0 || strpos($line, 'DB_') === 0) {
                        echo "   $line\n";
                    }
                }
            }
        } else {
            echo "❌ $description ($file) - Manquant\n";
        }
    }
    
    echo "\n";
    
    // 4. Tester la structure des répertoires
    echo "📋 === VÉRIFICATION DE LA STRUCTURE DES RÉPERTOIRES ===\n";
    
    $directories = [
        'app/Http/Controllers',
        'app/Models',
        'routes',
        'config',
        'bootstrap'
    ];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "✅ $dir/ - Existe\n";
            $files = scandir($dir);
            $phpFiles = array_filter($files, function($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'php';
            });
            echo "   Fichiers PHP: " . count($phpFiles) . "\n";
        } else {
            echo "❌ $dir/ - Manquant\n";
        }
    }
    
    echo "\n";
    
    // 5. Suggestions de résolution
    echo "💡 === SUGGESTIONS DE RÉSOLUTION ===\n";
    
    // Vérifier si les routes API sont définies
    if (file_exists('routes/api.php')) {
        $apiContent = file_get_contents('routes/api.php');
        if (strpos($apiContent, 'referral') === false) {
            echo "⚠️ Aucune route 'referral' trouvée dans routes/api.php\n";
            echo "   → Il faut ajouter les routes API pour les modules\n";
        } else {
            echo "✅ Routes 'referral' trouvées dans routes/api.php\n";
        }
        
        if (strpos($apiContent, 'Route::') === false) {
            echo "⚠️ Aucune définition de route trouvée dans routes/api.php\n";
            echo "   → Le fichier semble vide ou mal configuré\n";
        } else {
            echo "✅ Définitions de routes trouvées dans routes/api.php\n";
        }
    }
    
    // Vérifier les contrôleurs
    $missingControllers = [];
    foreach ($controllers as $controller) {
        if (!file_exists($controller)) {
            $missingControllers[] = basename($controller, '.php');
        }
    }
    
    if (!empty($missingControllers)) {
        echo "⚠️ Contrôleurs manquants: " . implode(', ', $missingControllers) . "\n";
        echo "   → Il faut créer ces contrôleurs\n";
    }
    
    echo "\n";
    
    // 6. Commandes de diagnostic Laravel
    echo "📋 === COMMANDES DE DIAGNOSTIC LARAVEL ===\n";
    echo "Pour diagnostiquer plus en détail, exécutez ces commandes:\n";
    echo "1. php artisan route:list --path=api\n";
    echo "2. php artisan config:cache\n";
    echo "3. php artisan route:cache\n";
    echo "4. php artisan serve --host=0.0.0.0 --port=8000\n";
    echo "\n";
    
    // 7. Test de base Laravel
    echo "📋 === TEST DE BASE LARAVEL ===\n";
    echo "Test si Laravel peut démarrer...\n";
    
    // Essayer de charger Laravel
    if (file_exists('vendor/autoload.php') && file_exists('bootstrap/app.php')) {
        echo "✅ Fichiers Laravel de base présents\n";
        
        // Vérifier composer
        if (file_exists('composer.json')) {
            echo "✅ composer.json présent\n";
            $composerContent = json_decode(file_get_contents('composer.json'), true);
            if (isset($composerContent['require']['laravel/framework'])) {
                echo "✅ Laravel framework dans les dépendances\n";
            } else {
                echo "⚠️ Laravel framework non trouvé dans composer.json\n";
            }
        }
    } else {
        echo "❌ Fichiers Laravel de base manquants\n";
        echo "   → Exécutez 'composer install' pour installer les dépendances\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur lors du diagnostic: " . $e->getMessage() . "\n";
}
