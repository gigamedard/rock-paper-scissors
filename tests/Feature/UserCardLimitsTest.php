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
        $this->assertEquals(100.0, $response->json('active_limits.max_base_bet'));
        // Sans filleuls validés, plafond Q par défaut = 1.2
        $this->assertEquals(1.2, $response->json('active_limits.max_q'));
        // Cooldown FIXE par palier : défaut 3 jours (4320 min)
        $this->assertEquals(4320, $response->json('active_limits.min_cooldown'));
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
        // 100.0 (plafond base) + 0.04 (carte) = 100.04
        $this->assertEquals(100.04, $response->json('active_limits.max_base_bet'));
        // 1.2 (base, pas de filleuls) + 1.0 (carte) = 2.2 (< hard cap 3.0)
        $this->assertEquals(2.2, $response->json('active_limits.max_q'));
        // Cooldown FIXE par palier (défaut 3 jours = 4320 min)
        $this->assertEquals(4320, $response->json('active_limits.min_cooldown'));
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
            'bet_amount' => 0.0004,
            'cid' => 'QmTest',
            'target_q' => 1.2,
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
            'bet_amount' => 200, // Exceeds default max_base_bet of 100
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
            'bet_amount' => 0.0004,
            'target_q' => 2.0, // Exceeds default max_q of 1.2 (no validated referrals)
            'cid' => 'QmTest',
        ]);

        $response->assertStatus(422);
    }

    public function test_cooldown_is_fixed_per_tier_and_not_player_chosen()
    {
        $user = User::factory()->create(['status' => 'available']);
        $token = ApiToken::generateForUser($user, 60);

        // Le joueur ne choisit plus le cooldown : la valeur transmise est ignorée
        // et remplacée par le cooldown fixe du palier.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/pre-moves', [
            'user_id' => $user->id,
            'pre_moves' => ['rock', 'paper', 'scissors'],
            'bet_amount' => 0.0004,
            'cooldown_time' => 720, // ignoré
            'cid' => 'QmTest',
        ]);

        $response->assertStatus(200);
    }

    public function test_cards_are_not_consumed_until_session_ends()
    {
        $user = User::factory()->create([
            'balance' => 10,
            'battle_balance' => 0,
            'session_start_balance' => 10,
            'session_start_battle_balance' => 0,
            'session_started' => true,
            'wallet_address' => '0xUserCardTest',
            'multiplier_level' => 2,
            'recovery_level' => 1,
            'bet_amount' => 1,
            'target_q' => 1.2,
            'status' => 'in_pool',
            'autoplay_active' => true,
        ]);

        $card = Card::create([
            'name' => 'Base Bet Boost',
            'effect_type' => 'base_bet_modifier',
            'effect_value' => 0.04,
            'duration_type' => 'sessions',
            'duration_value' => 3,
            'price' => 10,
            'is_active' => true,
        ]);

        $userCard = UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'status' => 'available',
            'remaining_sessions' => 3,
        ]);

        $pool = \App\Models\Pool::factory()->create(['base_bet' => 1, 'pool_size' => 2]);
        $user->pool_id = $pool->id;
        $user->save();

        $dummyUser = User::factory()->create([
            'balance' => 100,
            'battle_balance' => 10,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 10,
            'session_started' => true,
            'bet_amount' => 1,
            'status' => 'in_pool',
            'autoplay_active' => true,
        ]);

        \App\Models\Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user->id,
            'user2_id' => $dummyUser->id,
            'base_bet_amount' => 1,
            'status' => 'completed',
        ]);

        $web3Mock = \Mockery::mock(\App\Helpers\Web3Helper::class);
        $web3Mock->shouldReceive('sendPayement')->byDefault();
        $web3Mock->shouldReceive('setUserLimits')->byDefault();
        $web3Mock->shouldReceive('setUserNextSessionTime')->byDefault();

        $historyMock = \Mockery::mock(\App\Services\SessionHistoryService::class);
        $historyMock->shouldReceive('archiveSessionHistory')->byDefault();

        $notificationMock = \Mockery::mock(\App\Services\NotificationService::class);
        $signatureMock = \Mockery::mock(\App\Services\SignatureService::class);

        $sessionManager = new \App\Services\SessionManager(
            $web3Mock,
            $historyMock,
            $notificationMock,
            $signatureMock
        );

        $user->battle_balance = 1;
        $user->save();

        $sessionManager->evaluatePoolEnd($pool);

        $userCard->refresh();
        $user->refresh();

        $this->assertEquals(3, $userCard->remaining_sessions);
        $this->assertEquals('available', $userCard->status);
        $this->assertTrue((bool)$user->session_started);

        $user->battle_balance = 20;
        $user->save();

        $pool2 = \App\Models\Pool::factory()->create(['base_bet' => 1, 'pool_size' => 2]);
        $user->pool_id = $pool2->id;
        $user->save();

        \App\Models\Fight::create([
            'pool_id' => $pool2->id,
            'user1_id' => $user->id,
            'user2_id' => $dummyUser->id,
            'base_bet_amount' => 1,
            'status' => 'completed',
        ]);

        $sessionManager->evaluatePoolEnd($pool2);

        $userCard->refresh();
        $user->refresh();

        $this->assertEquals(2, $userCard->remaining_sessions);
        $this->assertFalse((bool)$user->session_started);
    }
}
