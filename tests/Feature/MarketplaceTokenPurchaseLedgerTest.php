<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ledger token_purchases sur l'achat marketplace (arbitrage Q1) :
 * - tx_hash requis + unique
 * - vérification on-chain via le bridge avant crédit
 * - buyWithCredit / P2P n'écrivent PAS dans token_purchases (arbitrage Q2)
 */
class MarketplaceTokenPurchaseLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function actAsUser(): array
    {
        $user = User::factory()->create([
            'token_balance' => 0,
            'wallet_address' => '0x1234567890123456789012345678901234567890',
        ]);
        $token = ApiToken::generateForUser($user, 60);

        return [$user, $token];
    }

    public function test_purchase_requires_tx_hash(): void
    {
        [$user, $token] = $this->actAsUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/marketplace/purchase', [
            'amount' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tx_hash']);
        $this->assertSame(0, (int) $user->fresh()->token_balance);
        $this->assertSame(0, TokenPurchase::count());
    }

    public function test_purchase_rejects_already_used_tx_hash(): void
    {
        [$user, $token] = $this->actAsUser();

        TokenPurchase::create([
            'user_id' => $user->id,
            'amount' => 50,
            'source' => 'marketplace_purchase',
            'tx_hash' => '0xusedhash',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/marketplace/purchase', [
            'amount' => 100,
            'tx_hash' => '0xusedhash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tx_hash']);
        // Solde intact : aucune écriture avant vérification.
        $this->assertSame(50.0, (float) TokenPurchase::where('tx_hash', '0xusedhash')->value('amount'));
    }

    public function test_purchase_fails_without_chain_verification_and_credits_nothing(): void
    {
        [$user, $token] = $this->actAsUser();

        // Le bridge refuse la vérification (échec ou erreur).
        Http::fake([
            '*/verify-snt-transfer' => Http::response(['success' => false], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/marketplace/purchase', [
            'amount' => 100,
            'tx_hash' => '0xbadhash',
        ]);

        $response->assertStatus(422);
        $user->refresh();
        $this->assertEquals(0, $user->token_balance);
        $this->assertSame(0, TokenPurchase::count());
    }

    public function test_successful_purchase_credits_and_writes_ledger(): void
    {
        [$user, $token] = $this->actAsUser();

        Http::fake([
            '*/verify-snt-transfer' => Http::response(['success' => true]),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/marketplace/purchase', [
            'amount' => 100,
            'tx_hash' => '0xgoodhash',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(100, $user->fresh()->token_balance);

        $this->assertDatabaseHas('token_purchases', [
            'tx_hash' => '0xgoodhash',
            'user_id' => $user->id,
            'amount' => 100,
            'source' => 'marketplace_purchase',
            'quantity' => 1,
        ]);
    }

    public function test_buy_with_credit_does_not_write_ledger(): void
    {
        [$user, $token] = $this->actAsUser();

        $user->locked_balance = 1000;
        $user->save();

        $card = \App\Models\Card::create([
            'name' => 'Base Bet Boost',
            'effect_type' => 'base_bet_modifier',
            'effect_value' => 0.04,
            'duration_type' => 'sessions',
            'duration_value' => 3,
            'price' => 10,
            'is_active' => true,
        ]);

        Http::fake(['*' => Http::response(['success' => true])]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/shop/buy-with-credit', [
            'card_id' => $card->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);
        // Aucune écriture ledger : le crédit de jeu n'est pas un achat SNT on-chain (Q2).
        $this->assertSame(0, TokenPurchase::count());
    }
}
