<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pool;
use App\Models\User;
use App\Models\PreMove;
use App\Services\PoolService;
use Illuminate\Support\Facades\Log;

class TestMatchmaking extends Command
{
    /**
     * Le nom et la signature de la commande.
     * C'est ce que tu taperas dans le terminal.
     */
    protected $signature = 'test:run-matchmaking';

    /**
     * La description de la commande.
     */
    protected $description = 'Exécute un test d\'intégration manuel pour le matchmaking.';

    /**
     * Exécute la logique de la commande.
     */
    public function handle(PoolService $poolService)
    {
        $this->info('🚀 Démarrage du test de matchmaking...');
        Log::info('🚀 [TestMatchmaking] Démarrage du test...');

        try {
            // === 1. MISE EN SCÈNE ===
            $this->info('... 1. Préparation de la scène (Pool, Users, PreMoves)...');

            // 1. Trouve le dernier pool
            $pool = Pool::latest()->first();
            if (!$pool) {
                $this->error('❌ ERREUR: Aucun pool trouvé dans la base de données.');
                Log::error('❌ [TestMatchmaking] Aucun pool trouvé.');
                return 1; // Termine avec une erreur
            }
            $this->info("   - Utilisation du Pool ID: " . $pool->id);

            // 2. Récupère 2 utilisateurs de ce pool
            $user1 = $pool->users()->first();
            $user2 = $pool->users()->skip(1)->first();

            if (!$user1 || !$user2) {
                $this->error('❌ ERREUR: Le pool a besoin d\'au moins 2 utilisateurs pour ce test.');
                Log::error('❌ [TestMatchmaking] Le pool n\'a pas 2 utilisateurs.');
                return 1;
            }
            $this->info("   - Acteurs: User ID " . $user1->id . " et User ID " . $user2->id);

            // 3. Assure-toi qu'ils sont 'available'
            $user1->update(['status' => 'available']);
            $user2->update(['status' => 'available']);

            // 4. Donne-leur des pre-moves (rock vs paper = user2 gagne)
            PreMove::updateOrCreate(
                ['user_id' => $user1->id],
                ['moves' => ['rock', 'paper', 'scissors'], 'current_index' => 0]
            );

            PreMove::updateOrCreate(
                ['user_id' => $user2->id],
                ['moves' => ['paper', 'rock', 'scissors'], 'current_index' => 0]
            );
            $this->info('   - ✅ Scène prête !');


            // === 2. LANCEMENT DU TEST ===
            $this->info('... 2. Appel de PoolService->processPoolAutoMatch() ...');
            
            // Appelle la fonction ! (avec le pool_id et min 2 utilisateurs)
            $poolService->processPoolAutoMatch($pool->id, 2);

            $this->info('... 3. Appel de service terminé.');
            $this->info('... 4. Vérifiez vos terminaux (queue:work et logs) pour voir la chaîne d\'événements.');
            $this->info('🎉 TEST LANCÉ AVEC SUCCÈS !');
            Log::info('🎉 [TestMatchmaking] Test lancé.');

            return 0; // Termine avec succès

        } catch (\Exception $e) {
            $this->error('❌ ERREUR CATASTROPHIQUE PENDANT LE TEST:');
            $this->error($e->getMessage());
            Log::error('❌ [TestMatchmaking] ERREUR CATASTROPHIQUE: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }
    }
}