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

        // 3. Create Referee 1
        $referee1 = User::factory()->create();

        // 4. Create Pending Referral 1
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referee1->id,
            'referral_code' => 'INF123',
            'status' => 'pending'
        ]);

        // 5. Validate Referral 1 (Call API)
        $response1 = $this->postJson('/api/referrals/validate', [
            'user_id' => $referee1->id
        ]);
        $response1->assertStatus(200);

        // 6. Create Referee 2
        $referee2 = User::factory()->create();

        // 7. Create Pending Referral 2
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referee2->id,
            'referral_code' => 'INF123',
            'status' => 'pending'
        ]);

        // 8. Validate Referral 2 (Call API)
        $response2 = $this->postJson('/api/referrals/validate', [
            'user_id' => $referee2->id
        ]);
        $response2->assertStatus(200);

        // 9. Verify Stats
        $influencer->refresh();
        $this->assertNotNull($influencer->stats, 'Influencer stats should be created');
        $this->assertEquals(2, $influencer->stats->referral_count, 'Referral count should be 2');
    }
}
