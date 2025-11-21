<?php

namespace Tests\Feature;

use App\Events\SessionFinishedEvent;
use App\Listeners\SessionFinishedEventListener;
use App\Models\Pool;
use App\Models\User;
use App\Models\PreMove;
use App\Services\FightService;
use App\Services\PinataService;
use App\Helpers\Web3Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SessionPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected $web3HelperMock;
    protected $pinataServiceMock;
    protected $fightService;
    protected $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->web3HelperMock = Mockery::mock(Web3Helper::class);
        $this->pinataServiceMock = Mockery::mock(PinataService::class);
        
        // We use the real FightService but mock its dependencies if needed
        // Actually, we want to verify FightService::addUserToNewPool is called.
        // But addUserToNewPool uses Web3Helper too.
        
        $this->web3HelperMock->shouldReceive('premoveExists')->andReturn(true);
        $this->web3HelperMock->shouldReceive('sendPayement')->byDefault();
        $this->web3HelperMock->shouldReceive('sendSessionCIDToSmartContract')->byDefault();
        $this->pinataServiceMock->shouldReceive('pinJsonData')->andReturn('QmTestCid');

        $this->app->instance(Web3Helper::class, $this->web3HelperMock);
        $this->app->instance(PinataService::class, $this->pinataServiceMock);

        $this->fightService = app(FightService::class);
        $this->listener = app(SessionFinishedEventListener::class);
    }

    public function test_it_pays_out_when_threshold_is_reached()
    {
        // Arrange
        Config::set('game_settings.gain_coefficient', 1.5);

        $user = User::factory()->create([
            'balance' => 20, // Final balance
            'battle_balance' => 0,
            'session_start_balance' => 10,
            'session_start_battle_balance' => 0,
            'session_started' => true,
            'wallet_address' => '0xUser1',
        ]);
        
        PreMove::factory()->create([
            'user_id' => $user->id,
            'moves' => json_encode(['rock', 'paper', 'scissors']),
        ]);
        
        $pool = Pool::factory()->create(['base_bet' => 1, 'pool_size' => 5]);
        $user->pool_id = $pool->id; // User is in a pool
        $user->save();

        // Q = 20 / 10 = 2.0 >= 1.5 -> Threshold Reached

        $this->web3HelperMock->shouldReceive('sendPayement')
            ->once()
            ->with(Mockery::any(), '0xUser1', 20);

        // Act
        $event = new SessionFinishedEvent($user->id);
        $this->listener->handle($event);

        // Assert
        $user->refresh();
        $this->assertNull($user->pool_id);
        $this->assertFalse((bool)$user->session_started);
        $this->assertEquals('available', $user->status);
    }

    public function test_it_continues_session_when_threshold_is_not_reached()
    {
        // Arrange
        Config::set('game_settings.gain_coefficient', 3.0); // High threshold

        $user = User::factory()->create([
            'balance' => 15, // Final balance
            'battle_balance' => 0,
            'session_start_balance' => 10,
            'session_start_battle_balance' => 0,
            'session_started' => true,
            'wallet_address' => '0xUser2',
        ]);
        
        PreMove::factory()->create([
            'user_id' => $user->id,
            'moves' => json_encode(['rock', 'paper', 'scissors']),
        ]);

        $pool = Pool::factory()->create(['base_bet' => 1, 'pool_size' => 5, 'status' => 'from_server_finished']);
        $user->pool_id = $pool->id;
        $user->save();

        // Q = 15 / 10 = 1.5 < 3.0 -> Threshold NOT Reached

        $this->web3HelperMock->shouldNotReceive('sendPayement');

        // Act
        $event = new SessionFinishedEvent($user->id);
        $this->listener->handle($event);

        // Assert
        $user->refresh();
        // Should be in a NEW pool (or at least pool_id changed/re-assigned)
        // Since addUserToNewPool creates a new pool if needed
        $this->assertNotNull($user->pool_id);
        $this->assertNotEquals($pool->id, $user->pool_id);
        
        $this->assertTrue((bool)$user->session_started);
        // Status might be 'in_pool' or 'from_server_waitting' depending on logic, 
        // but definitely not 'available' in the sense of "reset".
        // Actually addUserToNewPool doesn't set status to 'in_pool' explicitly for user?
        // Let's check logic: User::where('id', $userId)->update(['pool_id' => $pool->id]);
        // It doesn't change user status. But user was 'in_pool' before.
        // So it remains 'in_pool'.
    }
}
