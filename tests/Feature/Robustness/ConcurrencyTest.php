<?php

namespace Tests\Feature\Robustness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Support\Str;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_pool_concurrency_lock()
    {
        $user = User::factory()->create([
            'status' => 'available',
            'wallet_address' => '0xConcurrencyUser'
        ]);

        $plainTextToken = Str::random(60);
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addHours(24),
        ]);

        // Acquire the lock manually to simulate another request already processing it
        $lock = Cache::lock('join_pool_' . $user->id, 10);
        $lock->get(); 

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->postJson('/api/user/pre-moves', [
            'pre_moves' => ['ROCK', 'PAPER', 'SCISSORS', 'ROCK', 'PAPER'],
            'bet_amount' => 0.05,
            'user_id' => $user->id,
            'cid' => 'fake_cid'
        ]);

        // Expect it to fail with Too Many Requests
        $response->assertStatus(429);
        $response->assertJsonFragment(['error' => 'Action already in progress']);

        // Release lock
        $lock->release();
    }
    
    public function test_handle_claim_concurrency_lock()
    {
        $user = User::factory()->create([
            'status' => 'pending_claim',
            'wallet_address' => '0xclaimconcurrencyuser'
        ]);
        
        $lock = Cache::lock('claim_pool_' . $user->id, 10);
        $lock->get();
        
        config(['app.INTERNAL_API_SECRET' => 'test_secret']);

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret'
        ])->postJson('/api/internal/handle-claim', [
            'wallet_address' => '0xclaimconcurrencyuser'
        ]);
        
        $response->assertStatus(429);
        $response->assertJsonFragment(['error' => 'Action already in progress']);
        
        $lock->release();
    }
}
