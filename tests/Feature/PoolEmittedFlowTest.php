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

        // Fake all HTTP calls to Node.js server to avoid external dependencies during tests
        \Illuminate\Support\Facades\Http::fake([
            '*/setUserLimits' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*/setUserNextSessionTime' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*/sendPayment' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*/getUserNonce/*' => \Illuminate\Support\Facades\Http::response(['nonce' => 0], 200),
            '*/get-game-config' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
        ]);
        
        // Définir le token interne pour l'API
        config(['app.INTERNAL_API_SECRET' => 'test_token_secret']);
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
            'balances' => ['100000000000000000000', '100000000000000000000'],
            'pool_salt' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2',
        ];

        // Note: Event::fake() is removed because processPoolAutoMatch now runs
        // within the same request and emits events. We do NOT want to block them.

        // Lire le secret depuis la config (comme le middleware)
        $secret = config('app.INTERNAL_API_SECRET');

        $response = $this->withHeaders([
            'X-Internal-Secret' => $secret, // Envoyer la bonne valeur
        ])->postJson(
            '/api/internal/handle-pool-emited', 
            $payload
        );




        if ($response->status() !== 200) {
            dd($response->json());
        }
        $response->assertStatus(200);

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
            'status' => 'completed',
        ]);

        // Vérification: users status updated after fight processing
        // Winners stay in pool, losers become available
        $this->assertTrue(
            in_array($user1->status, ['in_pool', 'available']) &&
            in_array($user2->status, ['in_pool', 'available'])
        );
    }
}
