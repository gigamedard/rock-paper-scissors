<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class TestApiIntegration extends Command
{
    protected $signature = 'test:api-integration {--host=localhost:8000 : Host pour les tests API}';
    protected $description = 'Test l\'intégration des API avec les pages HTML';

    private $baseUrl;
    private $testResults = [];

    public function handle()
    {
        $host = $this->option('host');
        $this->baseUrl = "http://{$host}";
        
        $this->info("🧪 Test d'intégration API - Base URL: {$this->baseUrl}");
        $this->newLine();

        // Vérifier que le serveur répond
        if (!$this->checkServerStatus()) {
            $this->error('❌ Serveur non accessible. Assurez-vous que le serveur Laravel est démarré.');
            return 1;
        }

        // Tests des API
        $this->testReferralApi();
        $this->testWhitelistApi();
        $this->testInfluencerApi();
        $this->testEscrowApi();
        
        // Tests des pages HTML
        $this->testHtmlPages();

        // Afficher le résumé
        $this->displaySummary();

        return 0;
    }

    private function checkServerStatus()
    {
        $this->info('🔍 Vérification du statut du serveur...');
        
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/");
            $this->addResult('Server Status', $response->successful(), 'Serveur accessible');
            return $response->successful();
        } catch (\Exception $e) {
            $this->addResult('Server Status', false, "Erreur: {$e->getMessage()}");
            return false;
        }
    }

    private function testReferralApi()
    {
        $this->info('🤝 Test des API de Parrainage...');

        // Test du classement (endpoint public)
        try {
            $response = Http::get("{$this->baseUrl}/api/referral/leaderboard");
            $success = $response->successful();
            $data = $response->json();
            
            $this->addResult(
                'Referral Leaderboard API', 
                $success && is_array($data) && count($data) > 0,
                $success ? "✅ " . count($data) . " entrées trouvées" : "❌ Échec de la requête"
            );

            if ($success && is_array($data)) {
                $this->line("   📊 Top 3 parraineurs:");
                foreach (array_slice($data, 0, 3) as $user) {
                    $this->line("      {$user['rank']}. {$user['name']} - {$user['referral_count']} parrainages");
                }
            }
        } catch (\Exception $e) {
            $this->addResult('Referral Leaderboard API', false, "Erreur: {$e->getMessage()}");
        }

        // Test avec authentification (simulation)
        $testUser = User::where('email', 'test@rockpaperscissors.com')->first();
        if ($testUser) {
            $this->line("   👤 Utilisateur de test trouvé: {$testUser->name} ({$testUser->referral_code})");
        }
    }

    private function testWhitelistApi()
    {
        $this->info('🎯 Test des API Whitelist...');

        // Test de la whitelist générale
        try {
            $response = Http::get("{$this->baseUrl}/api/whitelist");
            $success = $response->successful();
            $data = $response->json();
            
            $this->addResult(
                'Whitelist API', 
                $success,
                $success ? "✅ Merkle root: " . substr($data['merkle_root'] ?? 'N/A', 0, 10) . "..." : "❌ Échec"
            );

            if ($success && isset($data['total_addresses'])) {
                $this->line("   📋 {$data['total_addresses']} adresses whitelistées");
            }
        } catch (\Exception $e) {
            $this->addResult('Whitelist API', false, "Erreur: {$e->getMessage()}");
        }

        // Test de preuve pour une adresse spécifique
        try {
            $testAddress = '0x0000000000000000000000000000000000000001';
            $response = Http::get("{$this->baseUrl}/api/whitelist/proof/{$testAddress}");
            $success = $response->successful();
            
            $this->addResult(
                'Whitelist Proof API', 
                $success || $response->status() === 404,
                $success ? "✅ Preuve trouvée" : "ℹ️ Adresse non whitelistée (normal)"
            );
        } catch (\Exception $e) {
            $this->addResult('Whitelist Proof API', false, "Erreur: {$e->getMessage()}");
        }
    }

    private function testInfluencerApi()
    {
        $this->info('🏆 Test des API Influenceur...');

        // Test des pools
        try {
            $response = Http::get("{$this->baseUrl}/api/influencer/pools");
            $success = $response->successful();
            $data = $response->json();
            
            $this->addResult(
                'Influencer Pools API', 
                $success && is_array($data),
                $success ? "✅ " . count($data) . " pools trouvés" : "❌ Échec"
            );

            if ($success && is_array($data)) {
                $this->line("   🌍 Pools disponibles:");
                foreach ($data as $pool) {
                    $progress = $pool['progress_percentage'] ?? 0;
                    $this->line("      • {$pool['name']} ({$pool['language']}) - {$progress}% complété");
                }
            }
        } catch (\Exception $e) {
            $this->addResult('Influencer Pools API', false, "Erreur: {$e->getMessage()}");
        }

        // Test du classement
        try {
            $response = Http::get("{$this->baseUrl}/api/influencer/leaderboard");
            $success = $response->successful();
            $data = $response->json();
            
            $this->addResult(
                'Influencer Leaderboard API', 
                $success && is_array($data),
                $success ? "✅ " . count($data) . " influenceurs classés" : "❌ Échec"
            );
        } catch (\Exception $e) {
            $this->addResult('Influencer Leaderboard API', false, "Erreur: {$e->getMessage()}");
        }
    }

    private function testEscrowApi()
    {
        $this->info('💱 Test des API Escrow P2P...');

        // Test des statistiques
        try {
            $response = Http::get("{$this->baseUrl}/api/escrow/stats");
            $success = $response->successful();
            $data = $response->json();
            
            $this->addResult(
                'Escrow Stats API', 
                $success && isset($data['total_trades']),
                $success ? "✅ {$data['total_trades']} trades totaux" : "❌ Échec"
            );

            if ($success) {
                $this->line("   📊 Statistiques du marketplace:");
                $this->line("      • Trades actifs: {$data['active_trades']}");
                $this->line("      • Volume SNT: {$data['total_snt_volume']}");
                $this->line("      • Volume AVAX: {$data['total_avax_volume']}");
            }
        } catch (\Exception $e) {
            $this->addResult('Escrow Stats API', false, "Erreur: {$e->getMessage()}");
        }

        // Test des trades
        try {
            $response = Http::get("{$this->baseUrl}/api/escrow/trades");
            $success = $response->successful();
            $data = $response->json();
            
            $this->addResult(
                'Escrow Trades API', 
                $success && isset($data['trades']),
                $success ? "✅ " . count($data['trades']) . " trades disponibles" : "❌ Échec"
            );
        } catch (\Exception $e) {
            $this->addResult('Escrow Trades API', false, "Erreur: {$e->getMessage()}");
        }
    }

    private function testHtmlPages()
    {
        $this->info('🌐 Test des pages HTML...');

        $pages = [
            'index.html' => 'Page d\'accueil',
            'referral.html' => 'Dashboard Parrainage',
            'influencer.html' => 'Dashboard Influenceur',
            'marketplace.html' => 'Marketplace P2P',
            'test.php' => 'Page de test technique',
        ];

        foreach ($pages as $page => $description) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/{$page}");
                $success = $response->successful();
                $content = $response->body();
                
                // Vérifications spécifiques du contenu
                $hasContent = strlen($content) > 1000; // Au moins 1KB de contenu
                $hasTitle = strpos($content, '<title>') !== false;
                $hasModuleContent = strpos($content, 'Modules') !== false || 
                                  strpos($content, 'Parrainage') !== false ||
                                  strpos($content, 'Influenceur') !== false;
                
                $this->addResult(
                    "Page {$page}", 
                    $success && $hasContent && $hasTitle,
                    $success ? "✅ {$description} accessible" : "❌ Page non accessible"
                );

                if ($success && $hasModuleContent) {
                    $this->line("   📄 Contenu des modules détecté");
                }
            } catch (\Exception $e) {
                $this->addResult("Page {$page}", false, "Erreur: {$e->getMessage()}");
            }
        }
    }

    private function addResult($test, $success, $message)
    {
        $this->testResults[] = [
            'test' => $test,
            'success' => $success,
            'message' => $message,
        ];

        $icon = $success ? '✅' : '❌';
        $this->line("   {$icon} {$test}: {$message}");
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info('📋 RÉSUMÉ DES TESTS');
        $this->line(str_repeat('=', 50));

        $totalTests = count($this->testResults);
        $successfulTests = collect($this->testResults)->where('success', true)->count();
        $failedTests = $totalTests - $successfulTests;

        $this->line("Total des tests: {$totalTests}");
        $this->line("✅ Réussis: {$successfulTests}");
        $this->line("❌ Échoués: {$failedTests}");
        
        $successRate = $totalTests > 0 ? round(($successfulTests / $totalTests) * 100, 1) : 0;
        $this->line("📊 Taux de réussite: {$successRate}%");

        if ($failedTests > 0) {
            $this->newLine();
            $this->warn('⚠️ TESTS ÉCHOUÉS:');
            foreach ($this->testResults as $result) {
                if (!$result['success']) {
                    $this->line("   • {$result['test']}: {$result['message']}");
                }
            }
        }

        $this->newLine();
        if ($successRate >= 80) {
            $this->info('🎉 Intégration API réussie ! Les modules sont fonctionnels.');
        } elseif ($successRate >= 60) {
            $this->warn('⚠️ Intégration partiellement réussie. Quelques problèmes à corriger.');
        } else {
            $this->error('❌ Problèmes d\'intégration détectés. Vérifiez la configuration.');
        }

        // Recommandations
        $this->newLine();
        $this->info('💡 RECOMMANDATIONS:');
        $this->line('• Vérifiez que le serveur Laravel est démarré: php artisan serve');
        $this->line('• Assurez-vous que la base de données est migrée: php artisan migrate');
        $this->line('• Exécutez le seeder de test: php artisan db:seed --class=TestDataSeeder');
        $this->line('• Testez les pages dans le navigateur: ' . $this->baseUrl);
    }
}

