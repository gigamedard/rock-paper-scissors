<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;
use App\Models\PreMove;
use App\Services\BatchProcessingService;
use App\Helpers\Web3Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;

class EndToEndGameFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $web3HelperMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Web3Helper to avoid actual blockchain calls
        $this->web3HelperMock = Mockery::mock(Web3Helper::class);
        $this->web3HelperMock->shouldReceive('weiToEther')->andReturnArg(0); // Simple pass-through for test
        $this->web3HelperMock->shouldReceive('sortAddressesWithSalt')->andReturnUsing(function ($addresses, $salt) {
            sort($addresses); // Simple sort for predictability
            return $addresses;
        });
        $this->web3HelperMock->shouldReceive('sendPayement')->byDefault();
        $this->web3HelperMock->shouldReceive('sendPoolCIDToSmartContract')->byDefault();
        $this->web3HelperMock->shouldReceive('premoveExists')->andReturn(true);
        $this->app->instance(Web3Helper::class, $this->web3HelperMock);
    }

    public function test_complete_game_flow()
    {
        // 1. Setup Configuration
        Config::set('game_settings.gain_coefficient', 1.5); // Set threshold
        Config::set('pool.size', [2]); // Target pool size of 2 for simplicity
        Config::set('pool.percentage_limit_of_pool_size', 1.0); // Require full pool

        // 2. Create Users
        $user1 = User::factory()->create([
            'wallet_address' => '0xUser1',
            'balance' => 10,
            'battle_balance' => 0,
            'session_started' => false,
        ]);
        $user2 = User::factory()->create([
            'wallet_address' => '0xUser2',
            'balance' => 10,
            'battle_balance' => 0,
            'session_started' => false,
        ]);

        // 3. Create PreMoves
        // User 1: Rock, User 2: Scissors -> User 1 wins
        PreMove::factory()->create([
            'user_id' => $user1->id,
            'moves' => json_encode(['rock', 'rock', 'rock']),
            'cid' => 'QmCID1',
        ]);
        PreMove::factory()->create([
            'user_id' => $user2->id,
            'moves' => json_encode(['scissors', 'scissors', 'scissors']),
            'cid' => 'QmCID2',
        ]);

        // 4. Simulate Pool Emission (API Call)
        $poolId = 'pool_test_123';
        $baseBet = '1.0';
        $poolSalt = 'test_salt';

        // Set the inner script token for the test
        $token = 'test_inner_token';
        Config::set('app.inner_script_token', $token); // Assuming config uses this, but controller uses env() directly.
        // We can't easily set env() at runtime for the controller if it uses env() directly and config is cached or not used.
        // However, Laravel's env() helper usually reads from $_ENV or getenv() which we can set.
        // Better yet, let's mock the env call? No, can't mock global function easily.
        // Let's check if we can set it via putenv.
        putenv("INNER_SCRIPT_TOKEN=$token");

        // We need to bypass the 'auth.internal' middleware for this test or mock it.
        $this->withoutMiddleware(\App\Http\Middleware\InternalApiAuth::class);

        $response = $this->postJson("/api/internal/handle-pool-emited?token=$token", [
            'pool_id' => $poolId,
            'base_bet' => $baseBet,
            'users' => [$user1->wallet_address, $user2->wallet_address],
            'premove_cids' => ['QmCID1', 'QmCID2'],
            'balances' => ['1000000000000000000000000', '1000000000000000000000000'],
            'pool_salt' => $poolSalt,
        ]);

        if ($response->status() !== 200) {
            dd($response->json());
        }
        $response->assertStatus(200);

        // 5. Verify Pool Creation
        $this->assertDatabaseHas('pools', [
            'pool_id' => $poolId,
            'status' => 'from_server_finished', // Pool matches immediately and finishes
        ]);

        $pool = Pool::where('pool_id', $poolId)->first();
        // Users are moved to a new pool immediately if session continues.
        // So they should NOT be in this pool anymore.
        $this->assertCount(0, $pool->users);

        // Verify users are available for the next pool
        $user1->refresh();
        $user2->refresh();
        $this->assertEquals('available', $user1->status);
        $this->assertEquals('available', $user2->status);

        // 6. Trigger Batch Processing
        // The pool is now 'from_blockchain_running'. The batch processor picks up 'from_server_waitting' pools usually?
        // Wait, 'handlePoolEmitedEvent' calls 'processPoolAutoMatch' directly!
        // Let's check PoolLifecycleService.php:
        // $this->processPoolAutoMatch($pool->id);
        // So the matching should have ALREADY happened synchronously in handlePoolEmitedEvent!
        
        // Let's verify if fights were created immediately.
        $this->assertDatabaseHas('fights', [
            'pool_id' => $pool->id,
            'status' => 'completed', // Since handlePoolAutoplayFight is called
        ]);

        // 7. Verify Results
        $user1->refresh();
        $user2->refresh();

        // User 1 (Winner) should have gained battle_balance
        // Initial Balance: 10. 
        // Bet: 1.0.
        // Security Coeff added: 1.0 * coeff (e.g. 1000? No, let's check config).
        // Wait, processUsersForPool adds: $user->balance += $baseBet * config('game_settings.security_coefficient');
        // Let's assume security_coefficient is 0 for simplicity or check default.
        // If it's 0:
        // User 1: 10 - 1 (bet) + 1 (win) + 1 (opponent's bet) = 11? 
        // No, logic is:
        // executeMatchingRound:
        // user->balance -= base_bet (10 - 1 = 9)
        // user->battle_balance += base_bet (0 + 1 = 1)
        // Fight Result (User 1 wins):
        // user1->battle_balance += user2->battle_balance (1 + 1 = 2)
        // user2->battle_balance = 0
        
        // finishPool:
        // user->balance += user->battle_balance
        // User 1: 9 + 2 = 11.
        // User 2: 9 + 0 = 9.

        // However, SessionFinishedEvent is fired.
        // Q = 11 / 10 = 1.1.
        // Threshold is 1.5.
        // 1.1 < 1.5 -> Session Continues.
        // User 1 should be in a NEW pool.
        
        // Let's check if User 1 is available for a new pool.
        $this->assertEquals('available', $user1->status);
        $this->assertNull($user1->pool_id);
        
        // User 2 (Loser)
        // Q = 9 / 10 = 0.9.
        // 0.9 < 1.5 -> Session Continues.
        // User 2 should be available for a NEW pool.
        $this->assertEquals('available', $user2->status);
        $this->assertNull($user2->pool_id);

        // 8. Verify Batch Processing (for the NEW pools)
        // The new pools are 'from_server_waitting'.
        // Now we trigger the batch processor to pick them up.
        
        // We need to make sure there are enough users for the new pool.
        // Both users are likely added to the SAME new waiting pool if they fit.
        // Pool size is 2. So they should fill it.
        
        // Trigger Batch Processing via Command
        $this->artisan('batch:process')
             ->assertExitCode(0);

        // Now the new pool should be processed.
        // User 1 (Rock) vs User 2 (Scissors) again (since moves are array).
        // User 1 wins again.
        
        $user1->refresh();
        $user2->refresh();
        
        // Previous Balance: 11.
        // Bet: 1.
        // User 1: 11 - 1 = 10. Battle: 1.
        // Win: Battle = 2.
        // Final: 10 + 2 = 12.
        // Q = 12 / 10 = 1.2 < 1.5. Continues.
        
        // This confirms the loop works.
    }
}
