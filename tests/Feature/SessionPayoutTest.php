<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use App\Models\Fight;
use App\Services\SessionManager;
use App\Services\SessionHistoryService;
use App\Services\NotificationService;
use App\Services\SignatureService;
use App\Helpers\Web3Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SessionPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected $web3HelperMock;
    protected $historyServiceMock;
    protected $notificationServiceMock;
    protected $signatureServiceMock;
    protected $sessionManager;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([], 200)
        ]);

        $this->web3HelperMock = Mockery::mock(Web3Helper::class);
        $this->web3HelperMock->shouldReceive('sendPayement')->byDefault();
        $this->web3HelperMock->shouldReceive('setUserNextSessionTime')->byDefault();
        $this->web3HelperMock->shouldReceive('setUserLimits')->byDefault();

        $this->historyServiceMock = Mockery::mock(SessionHistoryService::class);
        $this->historyServiceMock->shouldReceive('archiveSessionHistory')->byDefault();

        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->notificationServiceMock->shouldReceive('notifyInsufficientBalance')->byDefault();

        $this->signatureServiceMock = Mockery::mock(SignatureService::class);
        $this->signatureServiceMock->shouldReceive('generateClaimSignature')->byDefault();

        $this->sessionManager = new SessionManager(
            $this->web3HelperMock,
            $this->historyServiceMock,
            $this->notificationServiceMock,
            $this->signatureServiceMock
        );
    }

    public function test_it_pays_out_when_threshold_is_reached()
    {
        // Arrange
        Config::set('game_levels.multiplier.1', 1.5);
        Config::set('game_levels.recovery_time.1', 1440);

        $user = User::factory()->create([
            'balance' => 10,
            'battle_balance' => 10, // Won 10 in battle
            'session_start_balance' => 10,
            'session_start_battle_balance' => 0,
            'session_started' => true,
            'wallet_address' => '0xUser1',
            'multiplier_level' => 1,
            'recovery_level' => 1,
            'bet_amount' => 1,
            'status' => 'in_pool',
            'autoplay_active' => true, // force automatic payout mock path
        ]);

        $pool = Pool::factory()->create(['base_bet' => 1, 'pool_size' => 2]);
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

        // Create a mock fight to register user fought
        Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user->id,
            'user2_id' => $dummyUser->id,
            'base_bet_amount' => 1,
            'status' => 'completed',
        ]);

        // Q = (10 + 10) / 10 = 2.0 >= 1.5 -> Threshold Reached

        $this->web3HelperMock->shouldReceive('sendPayement')
            ->once()
            ->with(Mockery::any(), '0xUser1', 20.0);

        // Act
        $this->sessionManager->evaluatePoolEnd($pool);

        // Assert
        $user->refresh();
        $this->assertNull($user->pool_id);
        $this->assertFalse((bool)$user->session_started);
        $this->assertEquals('stopped', $user->status);
        $this->assertEquals(20.0, $user->balance);
        $this->assertEquals(0, $user->battle_balance);
    }

    public function test_it_continues_session_when_threshold_is_not_reached()
    {
        // Arrange
        Config::set('game_levels.multiplier.1', 3.0); // High threshold
        Config::set('game_levels.recovery_time.1', 1440);

        $user = User::factory()->create([
            'balance' => 10,
            'battle_balance' => 5, // Won 5 in battle
            'session_start_balance' => 10,
            'session_start_battle_balance' => 0,
            'session_started' => true,
            'wallet_address' => '0xUser2',
            'multiplier_level' => 1,
            'recovery_level' => 1,
            'bet_amount' => 1,
            'status' => 'in_pool',
            'autoplay_active' => true,
        ]);

        $pool = Pool::factory()->create(['base_bet' => 1, 'pool_size' => 2]);
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

        Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user->id,
            'user2_id' => $dummyUser->id,
            'base_bet_amount' => 1,
            'status' => 'completed',
        ]);

        // Q = (10 + 5) / 10 = 1.5 < 3.0 -> Threshold NOT Reached

        $this->web3HelperMock->shouldNotReceive('sendPayement');

        // Act
        $this->sessionManager->evaluatePoolEnd($pool);

        // Assert
        $user->refresh();
        $this->assertNull($user->pool_id);
        $this->assertTrue((bool)$user->session_started);
        $this->assertEquals('available', $user->status);
        $this->assertEquals(15.0, $user->balance);
        $this->assertEquals(0, $user->battle_balance);
    }
}
