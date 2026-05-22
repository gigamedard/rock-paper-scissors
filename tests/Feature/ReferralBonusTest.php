<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Influencer;
use App\Models\InfluencerPool;
use App\Models\InfluencerStat;

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

    public function test_referral_validation_rewards_and_eligibility()
    {
        // 1. Create Referrer
        $referrer = User::factory()->create([
            'referral_code' => 'REFP1',
            'token_balance' => 10,
            'locked_balance' => 0
        ]);

        // 2. Create Referee
        $referee = User::factory()->create([
            'token_balance' => 0,
            'locked_balance' => 0,
            'has_received_signup_bonus' => false,
            'is_eligible_to_refer' => false
        ]);

        // 3. Apply referral code first (creates pending referral)
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referee->id,
            'referral_code' => 'REFP1',
            'status' => 'pending'
        ]);

        // 4. Call manual validation endpoint (requires token.auth middleware)
        $admin = User::factory()->create();
        $token = \App\Models\ApiToken::generateForUser($admin, 60);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/referral/validate', [
            'user_id' => $referee->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Referral validated successfully. Referrer and Referred have been rewarded.'
        ]);

        // 5. Assert Referee updates
        $referee->refresh();
        $this->assertTrue((bool)$referee->is_eligible_to_refer, 'Referee should be marked eligible to refer');
        $this->assertTrue((bool)$referee->has_received_signup_bonus, 'Referee should receive signup bonus flag');
        $this->assertEquals(1, $referee->token_balance, 'Referee should receive 1 SNT signup bonus on validation');

        // 6. Assert Referrer updates (1st validated referral is a milestone)
        $referrer->refresh();
        $this->assertEquals(11, $referrer->token_balance, 'Referrer should get 1 SNT reward (10 + 1)');
        
        $rewardExists = ReferralReward::where('referrer_id', $referrer->id)
            ->where('milestone_reached', 1)
            ->exists();
        $this->assertTrue($rewardExists, 'ReferralReward record should exist for milestone 1');
    }

    public function test_influencer_stats_updated_on_validation()
    {
        // 1. Create Referrer who is an Influencer
        $referrer = User::factory()->create([
            'referral_code' => 'REFINF',
            'token_balance' => 10,
        ]);

        $pool = InfluencerPool::create([
            'name' => 'Test Pool',
            'language' => 'fr',
            'pool_milestone' => 1000,
            'reward_amount' => 50,
            'is_active' => true,
        ]);

        $influencer = Influencer::create([
            'user_id' => $referrer->id,
            'pool_id' => $pool->id,
            'milestone' => 10,
        ]);

        // 2. Create Referee
        $referee = User::factory()->create([
            'token_balance' => 0,
            'has_received_signup_bonus' => false
        ]);

        // 3. Create Pending Referral
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referee->id,
            'referral_code' => 'REFINF',
            'status' => 'pending'
        ]);

        // 4. Validate referral
        $service = app(\App\Services\ReferralService::class);
        $service->processReferralValidation($referee);

        // 5. Verify Influencer Stats are updated
        $stats = InfluencerStat::where('influencer_id', $influencer->id)->first();
        $this->assertNotNull($stats, 'Influencer stats should be created');
        $this->assertEquals(1, $stats->referral_count, 'Influencer referral count should be incremented');
    }

    public function test_get_reward_history_returns_history()
    {
        $user = User::factory()->create();
        $token = \App\Models\ApiToken::generateForUser($user, 60);

        ReferralReward::create([
            'referrer_id' => $user->id,
            'milestone_reached' => 1,
            'reward_tokens' => 10
        ]);

        ReferralReward::create([
            'referrer_id' => $user->id,
            'milestone_reached' => 3,
            'reward_tokens' => 30
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/referral/reward-history');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment([
            'milestone_reached' => 1,
            'reward_tokens' => 10
        ]);
        $response->assertJsonFragment([
            'milestone_reached' => 3,
            'reward_tokens' => 30
        ]);
    }
}
