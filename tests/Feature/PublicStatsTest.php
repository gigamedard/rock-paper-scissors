<?php

namespace Tests\Feature;

use App\Jobs\VerifyCardPurchaseJob;
use App\Models\Card;
use App\Models\TokenPurchase;
use App\Models\User;
use App\Models\UserCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le store 'array' vit au niveau du processus PHPUnit : le flush évite
        // de partager 'stats_public_v1' / 'stats_onchain_v1' entre les tests.
        Cache::flush();

        // PAS de Http::fake global dans setUp : les stubs Http::fake S'ACCUMULENT
        // (Factory::fake -> stubCallbacks->merge) et le PREMIER stub qui matche
        // gagne (PendingRequest::buildStubHandler -> map->filter->first). Un fake
        // catch-all ici masquerait les fakes plus spécifiques de chaque test.
    }

    private function normalizeCacheControlDirectives(string $header): array
    {
        // Symfony réordonne les directives Cache-Control : on trie pour une
        // assertion insensible à l'ordre.
        $parts = array_map('trim', explode(',', $header));
        sort($parts);

        return $parts;
    }

    public function test_stats_public_is_accessible_without_token_and_contains_core_keys(): void
    {
        // Bridge up, payload complet (avec la clé contractBalance attendue).
        Http::fake([
            '*/admin/contract-stats' => Http::response(['contractBalance' => '123.5', 'houseBalance' => '12.5'], 200),
        ]);

        $response = $this->getJson('/api/stats/public');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'generated_at',
            'on_chain' => ['tvl_snt', 'fees_snt', 'degraded'],
            'token_purchases' => [
                'all' => ['total_snt', 'count', 'unique_players', 'avg_per_player'],
                '30d' => ['total_snt', 'count', 'unique_players', 'avg_per_player'],
                '7d' => ['total_snt', 'count', 'unique_players', 'avg_per_player'],
                '24h' => ['total_snt', 'count', 'unique_players', 'avg_per_player'],
            ],
            'fights' => ['total', 'last_24h'],
            'players' => ['total', 'online'],
            'pools' => ['active'],
            'marketplace' => ['fulfilled_trades', 'total_snt', 'total_avax'],
        ]);

        // Payload on-chain complet -> tvl/fees non-null et degraded=false.
        $response->assertJsonPath('on_chain.tvl_snt', 123.5);
        $response->assertJsonPath('on_chain.fees_snt', 12.5);
        $response->assertJsonPath('on_chain.degraded', false);

        // Header anti-cache exigé par la spec (Symfony normalise l'ordre des
        // directives : on affirme le contenu, pas l'ordre).
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertSame(
            ['max-age=30', 'public', 's-maxage=45'], // ordre trié (Symfony réordonne)
            $this->normalizeCacheControlDirectives($cacheControl),
            "Cache-Control doit contenir exactement public, max-age=30, s-maxage=45 (reçu: {$cacheControl})"
        );
    }

    public function test_stats_public_marks_degraded_when_bridge_is_down(): void
    {
        // Bridge down : réponse 500 sur le endpoint stats du bridge.
        Http::fake([
            '*/admin/contract-stats' => Http::response('', 500),
        ]);

        $response = $this->getJson('/api/stats/public');

        $response->assertStatus(200);
        $response->assertJsonPath('on_chain.degraded', true);
        $response->assertJsonPath('on_chain.tvl_snt', null);
        $response->assertJsonPath('on_chain.fees_snt', null);
    }

    public function test_token_purchases_are_aggregated_per_window(): void
    {
        // Le bridge est requis (fragment on-chain) : payload valide.
        Http::fake([
            '*/admin/contract-stats' => Http::response(['contractBalance' => '10', 'houseBalance' => '1'], 200),
        ]);

        $user = User::factory()->create();
        // forceCreate : created_at n'est pas fillable, on fixe la date manuellement.
        TokenPurchase::forceCreate([
            'user_id' => $user->id,
            'amount' => 100.0,
            'quantity' => 1,
            'source' => 'marketplace_purchase',
            'tx_hash' => '0xfresh' . uniqid(),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
        TokenPurchase::forceCreate([
            'user_id' => $user->id,
            'amount' => 250.0,
            'quantity' => 1,
            'source' => 'card_purchase',
            'tx_hash' => '0xold' . uniqid(),
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $response = $this->getJson('/api/stats/public');

        $response->assertStatus(200);

        // Fenêtre 24h : seulement l'achat frais (1 achat, 1 joueur).
        // NB : le JSON peut sérialiser 100.0 en 100 selon les flags d'encodage,
        // on compare en float via un cast explicite.
        $this->assertSame(100.0, (float) $response->json('token_purchases.24h.total_snt'));
        $response->assertJsonPath('token_purchases.24h.count', 1);
        $response->assertJsonPath('token_purchases.24h.unique_players', 1);
        $this->assertSame(100.0, (float) $response->json('token_purchases.24h.avg_per_player'));

        // Fenêtre all : les deux achats, mais UN SEUL joueur unique.
        $this->assertSame(350.0, (float) $response->json('token_purchases.all.total_snt'));
        $response->assertJsonPath('token_purchases.all.count', 2);
        $response->assertJsonPath('token_purchases.all.unique_players', 1);
        $this->assertSame(350.0, (float) $response->json('token_purchases.all.avg_per_player'));
    }

    public function test_verify_card_purchase_job_writes_token_purchase_ledger_row(): void
    {
        Http::fake([
            '*/verify-snt-transfer' => Http::response(['success' => true]),
            // QUEUE_CONNECTION=sync : le job dispatche SyncUserLimitsJob inline
            // (->onQueue('limits')), qui appelle setUserLimits sur le bridge.
            '*/setUserLimits' => Http::response(['success' => true]),
        ]);

        $user = User::factory()->create([
            'wallet_address' => '0x1234567890123456789012345678901234567890',
        ]);

        $card = Card::create([
            'name' => 'Cooldown Card',
            'effect_type' => 'cooldown_reduction',
            'effect_value' => 720,
            'duration_type' => 'sessions',
            'duration_value' => 5,
            'price' => 15,
            'is_active' => true,
        ]);

        $userCard = UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'status' => 'pending',
            'remaining_sessions' => 5,
            'tx_hash' => '0xledgerhash123',
        ]);

        (new VerifyCardPurchaseJob($userCard))->handle();

        $this->assertDatabaseHas('token_purchases', [
            'tx_hash' => '0xledgerhash123',
            'user_id' => $user->id,
            'amount' => 15,
            'quantity' => 1,
            'source' => 'card_purchase',
        ]);
    }

    public function test_stats_response_is_cached_for_second_call(): void
    {
        Http::fake([
            '*/admin/contract-stats' => Http::response(['contractBalance' => '10', 'houseBalance' => '1'], 200),
        ]);

        $first = $this->getJson('/api/stats/public');
        $first->assertStatus(200);

        $second = $this->getJson('/api/stats/public');
        $second->assertStatus(200);

        // Cache hit : generated_at n'a pas été recalculé.
        $this->assertSame(
            $first->json('generated_at'),
            $second->json('generated_at'),
            'Le second appel doit être servi depuis le cache (même generated_at).'
        );
    }
}