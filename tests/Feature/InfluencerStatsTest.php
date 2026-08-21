<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Influencer;
use App\Models\InfluencerPool;
use App\Models\InfluencerStat;
use App\Models\Referral;

class InfluencerStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_influencer_stats_updated_on_referral_validation()
    {
        // 1. Create Pool
        $pool = InfluencerPool::create([
            'name' => 'Test Pool',
            'language' => 'en',
            'milestone' => 10,
            'pool_milestone' => 100,
            'reward_amount' => 1000,
            'is_active' => true
        ]);

        // 2. Create Referrer (Influencer)
        $referrer = User::factory()->create(['referral_code' => 'INF123']);
        
        $influencer = Influencer::create([
            'user_id' => $referrer->id,
            'pool_id' => $pool->id,
            'is_eligible' => true
        ]);

        // Initial stats (should be 0 or null)
        $this->assertNull($influencer->stats);

        // 3. Create Referee
        $referee = User::factory()->create();

        // 4. Create Pending Referral
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referee->id,
            'referral_code' => 'INF123',
            'status' => 'pending'
        ]);

        // 5. Validate Referral (Call API)
        $response = $this->postJson('/api/referrals/validate', [
            'user_id' => $referee->id
        ]);

        $response->assertStatus(200);

        // 6. Verify Stats
        $influencer->refresh();
        $this->assertNotNull($influencer->stats, 'Influencer stats should be created');
        $this->assertEquals(1, $influencer->stats->referral_count, 'Referral count should be 1');
    }
}
