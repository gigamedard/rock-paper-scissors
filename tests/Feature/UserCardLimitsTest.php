<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Card;
use App\Models\User;
use App\Models\UserCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserCardLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake all Node.js bridge HTTP calls by default
        Http::fake([
            '*/verify-snt-transfer' => Http::response(['success' => true]),
            '*/setUserLimits' => Http::response(['success' => true]),
            '*/setUserNextSessionTime' => Http::response(['success' => true]),
        ]);
    }

    public function test_user_profile_returns_default_limits_without_active_card()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);
        $this->assertEquals(0.01, $response->json('active_limits.max_base_bet'));
        $this->assertEquals(2.0, $response->json('active_limits.max_q'));
        $this->assertEquals(1440, $response->json('active_limits.min_cooldown'));
    }

    public function test_user_profile_returns_expanded_limits_with_active_card()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        // Create a card modifying base bet and another for target Q
        $card1 = Card::create([
            'name' => 'Base Bet Boost',
            'effect_type' => 'base_bet_modifier',
            'effect_value' => 0.04, // 0.01 + 0.04 = 0.05
            'duration_type' => 'sessions',
            'duration_value' => 3,
            'price' => 10,
            'is_active' => true,
        ]);

        $card2 = Card::create([
            'name' => 'Target Q Boost',
            'effect_type' => 'ceiling_increase',
            'effect_value' => 1.0, // 2.0 + 1.0 = 3.0
            'duration_type' => 'sessions',
            'duration_value' => 3,
            'price' => 10,
            'is_active' => true,
        ]);

        UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card1->id,
            'status' => 'available',
            'remaining_sessions' => 3,
        ]);

        UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card2->id,
            'status' => 'available',
            'remaining_sessions' => 3,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $response->assertStatus(200);
        $this->assertEquals(0.05, $response->json('active_limits.max_base_bet'));
        $this->assertEquals(3.0, $response->json('active_limits.max_q'));
        $this->assertEquals(1440, $response->json('active_limits.min_cooldown'));
    }

    public function test_buying_card_triggers_blockchain_limits_sync()
    {
        $user = User::factory()->create([
            'status' => 'available',
            'wallet_address' => '0x1234567890123456789012345678901234567890'
        ]);
        $token = ApiToken::generateForUser($user, 60);

        $card = Card::create([
            'name' => 'Cooldown Card',
            'effect_type' => 'cooldown_reduction',
            'effect_value' => 720, // reduces cooldown by 12h
            'duration_type' => 'sessions',
            'duration_value' => 5,
            'price' => 15,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/shop/buy', [
            'card_id' => $card->id,
            'tx_hash' => '0xuniquehash123'
        ]);

        $response->assertStatus(200);

        // Verify that setUserLimits request was sent to the bridge
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setUserLimits') &&
                   $request['wallet'] === '0x1234567890123456789012345678901234567890';
        });
    }

    public function test_starting_session_within_limits_succeeds()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/pre-moves', [
            'user_id' => $user->id,
            'pre_moves' => ['rock', 'paper', 'scissors'],
            'bet_amount' => 0.01,
            'cid' => 'QmTest',
            'target_q' => 2.0,
            'cooldown_time' => 1440
        ]);

        $response->assertStatus(200);
    }

    public function test_starting_session_exceeding_base_bet_limit_fails()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/pre-moves', [
            'user_id' => $user->id,
            'pre_moves' => ['rock', 'paper', 'scissors'],
            'bet_amount' => 0.02, // Exceeds default limit of 0.01
            'cid' => 'QmTest',
        ]);

        $response->assertStatus(422);
    }

    public function test_starting_session_exceeding_q_limit_fails()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/pre-moves', [
            'user_id' => $user->id,
            'pre_moves' => ['rock', 'paper', 'scissors'],
            'bet_amount' => 0.01,
            'target_q' => 2.5, // Exceeds default limit of 2.0
            'cid' => 'QmTest',
        ]);

        $response->assertStatus(422);
    }

    public function test_starting_session_exceeding_cooldown_limit_fails()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/pre-moves', [
            'user_id' => $user->id,
            'pre_moves' => ['rock', 'paper', 'scissors'],
            'bet_amount' => 0.01,
            'cooldown_time' => 720, // Less than default min cooldown 1440
            'cid' => 'QmTest',
        ]);

        $response->assertStatus(422);
    }
}
