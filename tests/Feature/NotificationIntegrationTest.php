<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;
use App\Services\InternalPoolService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;

class NotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $internalPoolService;
    protected $notificationMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure pool settings for test
        Config::set('pool.size', [2]);
        Config::set('pool.base_bet', [1.0]);
        Config::set('game_settings.abi', []); 
        
        // Mock Web3Helper
        $web3Mock = Mockery::mock(\App\Helpers\Web3Helper::class);
        $web3Mock->shouldReceive('premoveExists')->andReturn(true);
        $web3Mock->shouldReceive('sortAddressesWithSalt')->andReturnUsing(function($addrs, $salt) { 
            sort($addrs); return $addrs; 
        });
        $web3Mock->shouldReceive('weiToEther')->andReturn(1.0);
        $this->app->instance(\App\Helpers\Web3Helper::class, $web3Mock);

        // Mock PinataService
        $pinataMock = Mockery::mock(\App\Services\PinataService::class);
        $pinataMock->shouldReceive('pinJsonData')->andReturn('QmTestCID');
        $this->app->instance(\App\Services\PinataService::class, $pinataMock);

        // Mock NotificationService
        $this->notificationMock = Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $this->notificationMock);

        // Resolve service AFTER mocking dependencies
        $this->internalPoolService = app(InternalPoolService::class);
    }

    public function test_notifications_triggered_during_pool_lifecycle()
    {
        // 1. Expect Pool Entry Notifications
        // We expect 4 calls (4 users entering pools)
        $this->notificationMock->shouldReceive('notifyPoolEntry')
            ->times(4)
            ->with(Mockery::type(User::class), Mockery::type(Pool::class), 'new_session');

        // 2. Setup Users
        $users = User::factory()->count(4)->state(new \Illuminate\Database\Eloquent\Factories\Sequence(
            fn ($sequence) => ['wallet_address' => '0x' . \Illuminate\Support\Str::random(40) . $sequence->index]
        ))->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);
        
        foreach($users as $index => $user) {
            $moves = ($index % 2 == 0) ? ['rock', 'paper', 'scissors'] : ['scissors', 'rock', 'paper'];
            DB::table('pre_moves')->insert([
                'user_id' => $user->id,
                'moves' => json_encode($moves),
                'current_index' => 0,
                'cid' => 'QmTest',
                'session_first_pool_id' => 0
            ]);
        }

        // 3. Run Internal Pool Service (Triggers notifyPoolEntry)
        $result = $this->internalPoolService->processInternalPools(1.0);
        $this->assertEquals(2, $result['created_pools']);
        
        // 4. Setup expectations for Fight Wins
        // Each pool (size 2) has 1 fight. So 2 fights total. 2 winners.
        // We expect notifyFightWin called 2 times.
        $this->notificationMock->shouldReceive('notifyFightWin')
            ->times(2)
            ->with(Mockery::type(User::class), Mockery::any(), Mockery::any());

        // 5. Trigger Fights manually via PoolLifecycleService (simulating BatchProcessing)
        // We iterate created pools and run match()
        $pools = Pool::all();
        foreach ($pools as $pool) {
            $pool->match();
        }
    }
    
    public function test_notification_after_defeat()
    {
         // 1. Setup User who "lost" (index > 0)
         $user = User::factory()->create([
             'status' => 'available',
             'bet_amount' => 1.0,
             'autoplay_active' => true,
             'balance' => 10.0,
             'wallet_address' => '0x' . \Illuminate\Support\Str::random(40),
         ]);
         
         DB::table('pre_moves')->insert([
             'user_id' => $user->id,
             'moves' => json_encode(['rock', 'paper', 'scissors']),
             'current_index' => 1, // Simulate progressed index
             'cid' => 'QmTest',
             'session_first_pool_id' => 0
         ]);
         
         // Force size 1 for simplicity if possible, or create dummy filler
         Config::set('pool.size', [1]); 
         
         // Expect 'after_defeat' reason
         $this->notificationMock->shouldReceive('notifyPoolEntry')
            ->once()
            ->with(Mockery::type(User::class), Mockery::type(Pool::class), 'after_defeat');

         $result = $this->internalPoolService->processInternalPools(1.0);
    }
}
