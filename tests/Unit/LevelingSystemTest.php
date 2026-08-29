<?php

namespace Tests\Unit;

use App\Models\Pool;
use App\Models\User;
use App\Models\Fight;
use App\Jobs\ProcessPayoutJob;
use App\Jobs\SetCooldownJob;
use App\Services\SessionManager;
use App\Services\SessionHistoryService;
use App\Services\NotificationService;
use App\Services\SignatureService;
use App\Helpers\Web3Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class LevelingSystemTest extends TestCase
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

        Queue::fake();

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([], 200)
        ]);

        $this->web3HelperMock = Mockery::mock(Web3Helper::class);
        $this->web3HelperMock->shouldReceive('sendPayement')->byDefault();
        $this->web3HelperMock->shouldReceive('setUserNextSessionTime')->byDefault();
        $this->web3HelperMock->shouldReceive('setUserLimits')->byDefault();
        $this->app->instance(Web3Helper::class, $this->web3HelperMock);

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

    public function testListenerUsesCorrectMultiplierFromLevel()
    {
        // Setup Config
        Config::set('game_levels.multiplier.5', 7.5);
        Config::set('game_levels.recovery_time.1', 1440);

        // Create User in DB
        // Use a wallet address present in simulation_accounts.json so isBotUser() returns true
        // (bot index 0 from smart_contracts/simulation_accounts.json)
        $botWallet = '0x326593d1FF5F7c5Bb3B9ab995Eba9F3A30Da2F81';
        $user = User::factory()->create([
            'multiplier_level' => 5, // Should use 7.5x
            'recovery_level' => 1,
            'balance' => 100,
            'battle_balance' => 1000,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 0,
            'status' => 'in_pool',
            'wallet_address' => $botWallet,
            'autoplay_active' => true,
            'bet_amount' => 1,
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

        // Act
        $this->sessionManager->evaluatePoolEnd($pool);

        // Assert
        // Expect ProcessPayoutJob dispatched because Q = 1100 / 100 = 11 >= 7.5
        // (SessionManager::sendPayment dispatches ProcessPayoutJob, not Web3Helper directly)
        Queue::assertPushed(ProcessPayoutJob::class, function ($job) use ($botWallet) {
            return $job->walletAddress === $botWallet
                && abs($job->amount - 1100.0) < 0.01;
        });

        // Expect SetCooldownJob dispatched with correct cooldown based on recovery level 1 (1440 mins = 24 hours)
        Queue::assertPushed(SetCooldownJob::class, function ($job) use ($botWallet) {
            $diff = $job->nextTime - time();
            return $job->walletAddress === $botWallet && abs($diff - 86400) < 60;
        });

        $user->refresh();
        $this->assertNull($user->pool_id);
        $this->assertFalse((bool)$user->session_started);
        $this->assertEquals('stopped', $user->status);
    }
}
