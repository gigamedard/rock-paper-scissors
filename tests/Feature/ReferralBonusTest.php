<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class ReferralBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_referee_receives_locked_bonus()
    {
        // 1. Create Referrer
        $referrer = User::factory()->create([
            'referral_code' => 'REF123',
            'token_balance' => 0,
            'locked_balance' => 0
        ]);

        // 2. Create Referee
        $referee = User::factory()->create([
            'token_balance' => 0,
            'locked_balance' => 0,
            'has_received_signup_bonus' => false
        ]);

        // 3. Generate Token
        $token = \App\Models\ApiToken::generateForUser($referee, 60);

        // 4. Apply Referral Code
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/set-referral', [
            'referral_code' => 'REF123'
        ]);

        // 5. Assertions
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Referral code applied successfully! You received 1 SNT (Locked).']);

        $referee->refresh();
        $this->assertEquals(1, $referee->token_balance, 'Token balance should be 1');
        $this->assertEquals(1, $referee->locked_balance, 'Locked balance should be 1');
        $this->assertTrue((bool)$referee->has_received_signup_bonus, 'Bonus flag should be true');
    }

    public function test_bonus_only_given_once()
    {
        // 1. Create Referrer
        $referrer = User::factory()->create(['referral_code' => 'REF456']);

        // 2. Create Referee who already got bonus (simulated)
        $referee = User::factory()->create([
            'token_balance' => 10,
            'locked_balance' => 0,
            'has_received_signup_bonus' => true
        ]);

        // 3. Generate Token
        $token = \App\Models\ApiToken::generateForUser($referee, 60);

        // 4. Apply Referral Code
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/user/set-referral', [
            'referral_code' => 'REF456'
        ]);

        // 5. Assertions
        $response->assertStatus(200);
        // Should NOT get bonus message
        $response->assertJson(['message' => 'Referral code applied successfully!']);

        $referee->refresh();
        $this->assertEquals(10, $referee->token_balance, 'Token balance should not change');
    }
}
