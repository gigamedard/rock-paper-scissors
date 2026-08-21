<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;
use App\Models\PreMove;
use App\Events\FightCreatedEvent;
use App\Listeners\FightCreatedEventListener;
use App\Services\FightService;
use Illuminate\Support\Facades\Artisan;

class PoolEmittedFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Configuration initiale pour le test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // La base de données est gérée par le trait RefreshDatabase.
        // L'appel à migrate:fresh n'est pas nécessaire ici et cause un conflit avec SQLite.
        
        // Définir le token interne pour l'API
        config(['services.internal_api.secret' => 'test_token_secret']);
    }

    /**
     * Teste le flux complet de PoolEmitted à la résolution du combat.
     * Simule l'appel API et exécute le Listener manuellement.
     */
    public function test_pool_emitted_triggers_full_fight_cycle()
    {
        // --- 1. Préparation des données de test ---
        $baseBet = 0.01;
        $baseBetWei = '10000000000000000'; // 0.01 ETH en Wei (exemple)
        $securityCoefficient = 1.5; // Basé sur l'analyse de PoolService

        // Créer deux utilisateurs avec des adresses de portefeuille uniques
        $user1 = User::factory()->create([
            'wallet_address' => '0x1A00000000000000000000000000000000000001',
            'balance' => 100.0,
            'status' => 'available',
        ]);
        $user2 = User::factory()->create([
            'wallet_address' => '0x2B00000000000000000000000000000000000002',
            'balance' => 100.0,
            'status' => 'available',
        ]);

        // Créer les pre-moves pour les utilisateurs
        // User 1 gagne (rock vs scissors)
        $user1PreMove = PreMove::factory()->create([
            'user_id' => $user1->id,
            'cid' => 'cid_user1_rock',
            'moves' => ['rock', 'paper', 'scissors'],
            'current_index' => 0,
        ]);
        $user2PreMove = PreMove::factory()->create([
            'user_id' => $user2->id,
            'cid' => 'cid_user2_scissors',
            'moves' => ['scissors', 'rock', 'paper'],
            'current_index' => 0,
        ]);

        // Payload de l'API simulant l'événement blockchain
        $payload = [
            'pool_id' => '0xPOOLID12345',
            'base_bet' => $baseBetWei,
            'users' => [$user1->wallet_address, $user2->wallet_address],
            'premove_cids' => [$user1PreMove->cid, $user2PreMove->cid],
            'pool_salt' => '0xSALT12345',
        ];

        // Assurer que l'événement FightCreatedEvent est intercepté
        Event::fake();

        // Lire le secret depuis la config (comme le middleware)
        $secret = config('services.internal_api.secret');

        $response = $this->withHeaders([
            'X-Internal-Secret' => $secret, // Envoyer la bonne valeur
        ])->postJson(
            '/api/internal/handle-pool-emited', 
            $payload
        );




        // Vérification 1 (API) : L'API doit retourner un statut 200 OK
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Pool emitted Request handled successfully']);

        // --- 3. Vérification de la Création du Pool et des Fights (Étapes 4 & 5) ---

        // Vérification 2 (DB) : Pool créé
        $this->assertDatabaseHas('pools', [
            'pool_id' => $payload['pool_id'],
            'base_bet' => $baseBet,
            'pool_size' => 2,
        ]);

        $pool = Pool::where('pool_id', $payload['pool_id'])->first();

        // Vérification 3 (DB) : Fight créé
        $this->assertDatabaseHas('fights', [
            'pool_id' => $pool->id,
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
            'status' => 'waiting_for_result',
        ]);

        $fight = Fight::where('pool_id', $pool->id)->first();

        // Vérification 4 (DB) : Statuts des utilisateurs mis à jour
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'status' => 'locked',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'status' => 'locked',
        ]);

        // Vérification de l'événement déclenché
        Event::assertDispatched(FightCreatedEvent::class, function ($event) use ($fight) {
            return $event->fight->id === $fight->id;
        });

        // --- 4. Simulation du Traitement Asynchrone (Étapes 6 & 7) ---

        // Récupérer l'événement déclenché
        $events = Event::dispatched(FightCreatedEvent::class);
        $fightEvent = $events->first();

        // Exécuter le Listener manuellement (simule le Worker T2)
        $listener = $this->app->make(FightCreatedEventListener::class);
        $listener->handle($fightEvent);

        // --- 5. Vérification des Résultats Finaux (Étape 7) ---

        // Recharger les modèles pour obtenir les dernières données
        $user1->refresh();
        $user2->refresh();
        $fight->refresh();

        // Vérification 5 (DB) : Balances mises à jour
        // User 1 (rock) gagne contre User 2 (scissors)
        // battle_balance de User 1 doit être +baseBet
        // battle_balance de User 2 doit être -baseBet
        $this->assertEquals($baseBet, $user1->battle_balance);
        $this->assertEquals(-$baseBet, $user2->battle_balance);

        // Vérification du statut du combat
        $this->assertEquals('completed', $fight->status);
        $this->assertEquals('user1_win', $fight->result);
        $this->assertEquals('rock', $fight->user1_chosed);
        $this->assertEquals('scissors', $fight->user2_chosed);

        // Vérification 6 (DB) : FHist créé
        $this->assertDatabaseHas('f_hists', [
            'fight_id' => $fight->id,
            'user1_move' => 'rock',
            'user2_move' => 'scissors',
        ]);

        // Vérification 7 (DB) : Le perdant est libéré (status: available)
        // User 1 (gagnant) reste 'locked' pour le prochain combat du pool
        // User 2 (perdant) est remis 'available' pour être remis en file d'attente
        $this->assertEquals('locked', $user1->status);
        $this->assertEquals('available', $user2->status);
    }
}
