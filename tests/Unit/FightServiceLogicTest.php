<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fight;
use App\Models\User;
use App\Models\Pool;
use App\Services\FightService;
use App\Services\HistoricalFightService;
use App\Helpers\Web3Helper;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Mockery;

class FightServiceLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $fightService;
    protected $historicalFightService;
    protected $web3Helper;
    protected $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([], 200)
        ]);

        \App\Models\GameSetting::setValue('min_bet_eth', 5, 'float');
        \App\Models\GameSetting::setValue('max_martingale_level', 4, 'integer');

        // Mocks
        $this->historicalFightService = Mockery::mock(HistoricalFightService::class);
        $this->historicalFightService->shouldReceive('archiveFight')->andReturn(new \App\Models\FHist());
        
        $this->web3Helper = Mockery::mock(Web3Helper::class);
        $this->web3Helper->shouldReceive('sendPayement')->andReturn([]);
        $this->web3Helper->shouldReceive('setUserNextSessionTime')->andReturn([]);
        $this->web3Helper->shouldReceive('setUserLimits')->andReturn([]);
        $this->app->instance(Web3Helper::class, $this->web3Helper);
        
        $this->notificationService = Mockery::mock(NotificationService::class);
        $this->notificationService->shouldReceive('notifyFightWin')->andReturnNull();
        $this->notificationService->shouldReceive('notifyInsufficientBalance')->andReturnNull();
        $this->notificationService->shouldReceive('notifyFightStarted')->andReturnNull();

        $this->fightService = new FightService(
            $this->historicalFightService,
            $this->web3Helper,
            $this->notificationService
        );

        $this->sessionHistoryService = Mockery::mock(\App\Services\SessionHistoryService::class);
        $this->sessionHistoryService->shouldReceive('archiveSessionHistory')->andReturnNull();

        $this->signatureService = Mockery::mock(\App\Services\SignatureService::class);
        $this->signatureService->shouldReceive('generateClaimSignature')->andReturn('mock_signature');

        $this->sessionManager = new \App\Services\SessionManager(
            $this->web3Helper,
            $this->sessionHistoryService,
            $this->notificationService,
            $this->signatureService
        );

        // Pool must exist before users (FK constraint users.pool_id -> pools.id)
        $this->pool = Pool::create(['base_bet' => 5, 'pool_size' => 2, 'salt' => 'test', 'status' => 'from_server_running']);

        // Setup users (autoplay_active => true for automatic payout mock paths)
        $this->user1 = User::factory()->create([
            'balance' => 100,
            'battle_balance' => 10,
            'bet_amount' => 5,
            'status' => 'in_pool',
            'pool_id' => $this->pool->id,
            'autoplay_active' => true,
            'session_started' => true,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 10
        ]);
        $this->user2 = User::factory()->create([
            'balance' => 100,
            'battle_balance' => 10,
            'bet_amount' => 5,
            'status' => 'in_pool',
            'pool_id' => $this->pool->id,
            'autoplay_active' => true,
            'session_started' => true,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 10
        ]);

        // PreMoves
        DB::table('pre_moves')->insert([
            ['user_id' => $this->user1->id, 'moves' => json_encode(['rock']), 'current_index' => 0],
            ['user_id' => $this->user2->id, 'moves' => json_encode(['scissors']), 'current_index' => 0]
        ]);
    }

    public function testWinnerGainsBaseBetAndLoserLosesBaseBet()
    {
        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 5,
            'status' => 'waiting_for_both',
            'pool_id' => $this->pool->id
        ]);

        // User1 (Rock) vs User2 (Scissors) -> User1 Wins
        $this->fightService->handlePoolAutoplayFight($fight, 5, 2);

        $this->assertEquals(15, $this->user1->fresh()->battle_balance); // 10 + 5
        $this->assertEquals(5, $this->user2->fresh()->battle_balance);   // 10 - 5
    }

    public function testLoserEliminatedWhenBalanceZero()
    {
        // Set User2 balance to 5, so losing 5 makes it 0. Total funds large enough.
        $this->user2->update([
            'battle_balance' => 5,
            'balance' => 100,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 5
        ]);

        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 5,
            'status' => 'waiting_for_both',
            'pool_id' => $this->pool->id
        ]);

        $this->fightService->handlePoolAutoplayFight($fight, 5, 2);

        $this->sessionManager->evaluatePoolEnd($this->pool);

        $user2 = $this->user2->fresh();
        $this->assertEquals(0, $user2->battle_balance);
        $this->assertEquals('available', $user2->status); // Eliminated but available
        $this->assertNull($user2->pool_id);
        $this->assertEquals(10, $user2->bet_amount); // Doubled (5 * 2)
    }

    public function testLoserStoppedWhenTotalBalanceInsufficient()
    {
        // Set User2 balance so that total funds < 10 (next bet).
        // Lose 5. battle_balance becomes 0.
        // next bet = 10.
        // User needs balance + 0 >= 10.
        // So set balance = 5. Total = 5. Insufficient.
        $this->user2->update([
            'battle_balance' => 5,
            'balance' => 5,
            'session_start_balance' => 5,
            'session_start_battle_balance' => 5
        ]);

        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 5,
            'status' => 'waiting_for_both',
            'pool_id' => $this->pool->id
        ]);

        $this->fightService->handlePoolAutoplayFight($fight, 5, 2);

        $this->sessionManager->evaluatePoolEnd($this->pool);

        $user2 = $this->user2->fresh();
        $this->assertEquals(0, $user2->battle_balance);
        $this->assertEquals('stopped', $user2->status); // STOPPED
        $this->assertNull($user2->pool_id);
        $this->assertEquals(5, $user2->bet_amount);
    }

    public function testLoserEliminatedWhenBalanceBelowZero()
    {
        // Set User2 balance to 2, so losing 5 makes it -3
        $this->user2->update([
            'battle_balance' => 2,
            'balance' => 100,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 2
        ]);

        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 5,
            'status' => 'waiting_for_both',
            'pool_id' => $this->pool->id
        ]);

        $this->fightService->handlePoolAutoplayFight($fight, 5, 2);

        $this->sessionManager->evaluatePoolEnd($this->pool);

        $user2 = $this->user2->fresh();
        $this->assertEquals(0, $user2->battle_balance);
        $this->assertEquals('available', $user2->status);
        $this->assertNull($user2->pool_id);
        $this->assertEquals(10, $user2->bet_amount);
    }
    
    public function testLoserEliminatedWhenBalanceInsufficient()
    {
        // Set User2 balance to 9. Lose 5 -> 4. 4 < 5. Eliminated.
        $this->user2->update([
            'battle_balance' => 9,
            'balance' => 100,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 9
        ]);

        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 5,
            'status' => 'waiting_for_both',
            'pool_id' => $this->pool->id
        ]);

        $this->fightService->handlePoolAutoplayFight($fight, 5, 2);

        $this->sessionManager->evaluatePoolEnd($this->pool);

        $user2 = $this->user2->fresh();
        $this->assertEquals(0, $user2->battle_balance);
        $this->assertEquals('available', $user2->status); // Eliminated
        $this->assertNull($user2->pool_id);
        $this->assertEquals(10, $user2->bet_amount);
    }
    
    public function testLoserNotEliminatedWhenBalanceSufficient()
    {
        // Set User2 balance to 10. Lose 5 -> 5. 5 >= 5. Kept.
        $this->user2->update(['battle_balance' => 10]);

        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 5,
            'status' => 'waiting_for_both',
            'pool_id' => $this->pool->id
        ]);

        $this->fightService->handlePoolAutoplayFight($fight, 5, 2);

        $user2 = $this->user2->fresh();
        $this->assertEquals(5, $user2->battle_balance);
        $this->assertEquals('in_pool', $user2->status);
        $this->assertNotNull($user2->pool_id);
        $this->assertEquals(5, $user2->bet_amount); // Not doubled
    }
}
