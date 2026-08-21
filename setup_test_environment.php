<?php
/**
 * Script de Configuration de l'Environnement de Test
 * Rock Paper Scissors - Modules de Croissance et d'Économie
 */

echo "🚀 Configuration de l'environnement de test\n";
echo str_repeat('=', 50) . "\n";

// Vérifier que nous sommes dans le bon répertoire
if (!file_exists('artisan')) {
    echo "❌ Erreur: Ce script doit être exécuté depuis la racine du projet Laravel\n";
    exit(1);
}

// Étape 1: Vérifier les dépendances
echo "📋 Étape 1: Vérification des dépendances...\n";

$requiredFiles = [
    'database/migrations/2024_09_03_000001_create_referrals_table.php',
    'database/migrations/2024_09_03_000002_add_referral_code_to_users_table.php',
    'database/migrations/2024_09_03_000003_create_influencer_pools_table.php',
    'database/migrations/2024_09_03_000004_create_influencers_table.php',
    'database/migrations/2024_09_03_000005_create_influencer_stats_table.php',
    'app/Models/Referral.php',
    'app/Models/InfluencerPool.php',
    'app/Models/Influencer.php',
    'app/Models/InfluencerStat.php',
    'app/Http/Controllers/ReferralController.php',
    'app/Http/Controllers/InfluencerController.php',
    'app/Http/Controllers/EscrowController.php',
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        $missingFiles[] = $file;
    }
}

if (!empty($missingFiles)) {
    echo "❌ Fichiers manquants:\n";
    foreach ($missingFiles as $file) {
        echo "   • $file\n";
    }
    echo "Veuillez vous assurer que tous les modules sont correctement installés.\n";
    exit(1);
}

echo "✅ Tous les fichiers requis sont présents\n";

// Étape 2: Configuration de la base de données
echo "\n📋 Étape 2: Configuration de la base de données...\n";

// Vérifier le fichier .env
if (!file_exists('.env')) {
    if (file_exists('.env.example')) {
        copy('.env.example', '.env');
        echo "✅ Fichier .env créé depuis .env.example\n";
    } else {
        echo "❌ Fichier .env.example non trouvé\n";
        exit(1);
    }
}

// Configurer SQLite pour les tests
$envContent = file_get_contents('.env');

// Remplacer la configuration de base de données par SQLite
$envContent = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=sqlite', $envContent);
$envContent = preg_replace('/DB_HOST=.*/', '# DB_HOST=127.0.0.1', $envContent);
$envContent = preg_replace('/DB_PORT=.*/', '# DB_PORT=3306', $envContent);
$envContent = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . __DIR__ . '/database/database.sqlite', $envContent);
$envContent = preg_replace('/DB_USERNAME=.*/', '# DB_USERNAME=root', $envContent);
$envContent = preg_replace('/DB_PASSWORD=.*/', '# DB_PASSWORD=', $envContent);

file_put_contents('.env', $envContent);
echo "✅ Configuration SQLite mise à jour dans .env\n";

// Créer la base de données SQLite
$dbPath = __DIR__ . '/database/database.sqlite';
if (!file_exists($dbPath)) {
    touch($dbPath);
    echo "✅ Base de données SQLite créée: $dbPath\n";
}

// Étape 3: Exécuter les migrations
echo "\n📋 Étape 3: Exécution des migrations...\n";
$output = [];
$returnCode = 0;

exec('php artisan migrate:fresh --force 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Migrations exécutées avec succès\n";
} else {
    echo "❌ Erreur lors des migrations:\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
    exit(1);
}

// Étape 4: Exécuter le seeder
echo "\n📋 Étape 4: Peuplement de la base de données...\n";
$output = [];
$returnCode = 0;

exec('php artisan db:seed --class=TestDataSeeder --force 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Données de test créées avec succès\n";
} else {
    echo "❌ Erreur lors du seeding:\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
    // Continuer même si le seeding échoue
}

// Étape 5: Vérifier les données
echo "\n📋 Étape 5: Vérification des données...\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    
    // Compter les utilisateurs
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $userCount = $stmt->fetchColumn();
    echo "✅ Utilisateurs créés: $userCount\n";
    
    // Compter les parrainages
    $stmt = $pdo->query('SELECT COUNT(*) FROM referrals');
    $referralCount = $stmt->fetchColumn();
    echo "✅ Parrainages créés: $referralCount\n";
    
    // Compter les pools d'influenceurs
    $stmt = $pdo->query('SELECT COUNT(*) FROM influencer_pools');
    $poolCount = $stmt->fetchColumn();
    echo "✅ Pools d'influenceurs créés: $poolCount\n";
    
    // Compter les influenceurs
    $stmt = $pdo->query('SELECT COUNT(*) FROM influencers');
    $influencerCount = $stmt->fetchColumn();
    echo "✅ Influenceurs créés: $influencerCount\n";
    
} catch (Exception $e) {
    echo "⚠️ Impossible de vérifier les données: " . $e->getMessage() . "\n";
}

// Étape 6: Générer la clé d'application
echo "\n📋 Étape 6: Configuration de l'application...\n";
$output = [];
exec('php artisan key:generate --force 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Clé d'application générée\n";
} else {
    echo "⚠️ Problème avec la génération de clé (peut être ignoré)\n";
}

// Étape 7: Instructions finales
echo "\n🎉 CONFIGURATION TERMINÉE !\n";
echo str_repeat('=', 50) . "\n";
echo "📋 Prochaines étapes:\n\n";

echo "1. 🚀 Démarrer le serveur Laravel:\n";
echo "   php artisan serve --host=0.0.0.0 --port=8000\n\n";

echo "2. 🧪 Tester l'intégration API:\n";
echo "   php artisan test:api-integration\n\n";

echo "3. 🌐 Tester les pages dans le navigateur:\n";
echo "   • http://localhost:8000/index.html (Page d'accueil)\n";
echo "   • http://localhost:8000/referral.html (Dashboard Parrainage)\n";
echo "   • http://localhost:8000/influencer.html (Dashboard Influenceur)\n";
echo "   • http://localhost:8000/marketplace.html (Marketplace P2P)\n";
echo "   • http://localhost:8000/test.php (Page technique)\n\n";

echo "4. 📊 Tester les API directement:\n";
echo "   • GET /api/referral/leaderboard\n";
echo "   • GET /api/influencer/pools\n";
echo "   • GET /api/escrow/stats\n";
echo "   • GET /api/whitelist\n\n";

echo "5. 👤 Utilisateur de test disponible:\n";
echo "   • Email: test@rockpaperscissors.com\n";
echo "   • Mot de passe: password123\n";
echo "   • Code de parrainage: REF-TEST01\n\n";

echo "💡 Conseils:\n";
echo "• Utilisez les outils de développement du navigateur pour voir les requêtes API\n";
echo "• Vérifiez les logs Laravel en cas de problème: storage/logs/laravel.log\n";
echo "• Les données de test sont réinitialisées à chaque exécution de ce script\n\n";

echo "✅ Environnement de test prêt pour les modules de croissance et d'économie !\n";
?>

