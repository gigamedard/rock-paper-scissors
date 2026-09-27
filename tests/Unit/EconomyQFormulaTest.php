<?php

namespace Tests\Unit;

use App\Jobs\ProcessPayoutJob;
use App\Models\Fight;
use App\Models\Pool;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SessionHistoryService;
use App\Services\SessionManager;
use App\Services\SignatureService;
use App\Helpers\Web3Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EconomyQFormulaTest extends TestCase
{
    use RefreshDatabase;

    protected $sessionManager;

    // Adresse présente dans smart_contracts/simulation_accounts.json (bot index 0)
    private const BOT_WALLET = '0x326593d1FF5F7c5Bb3B9ab995Eba9F3A30Da2F81';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([], 200),
        ]);

        $web3HelperMock = Mockery::mock(Web3Helper::class);
        $web3HelperMock->shouldReceive('sendPayement')->byDefault();
        $web3HelperMock->shouldReceive('setUserNextSessionTime')->byDefault();
        $web3HelperMock->shouldReceive('setUserLimits')->byDefault();
        $this->app->instance(Web3Helper::class, $web3HelperMock);

        $historyServiceMock = Mockery::mock(SessionHistoryService::class);
        $historyServiceMock->shouldReceive('archiveSessionHistory')->byDefault();

        $notificationServiceMock = Mockery::mock(NotificationService::class);
        $notificationServiceMock->shouldReceive('notifyInsufficientBalance')->byDefault();

        $signatureServiceMock = Mockery::mock(SignatureService::class);
        $signatureServiceMock->shouldReceive('generateClaimSignature')->byDefault();

        $this->sessionManager = new SessionManager(
            $web3HelperMock,
            $historyServiceMock,
            $notificationServiceMock,
            $signatureServiceMock
        );
    }

    private function makeFoughedUser(float $balance, float $battleBalance, float $targetQ): array
    {
        $user = User::factory()->create([
            'wallet_address' => self::BOT_WALLET,
            'balance' => $balance,
            'battle_balance' => $battleBalance,
            'session_start_balance' => 10,
            'session_start_battle_balance' => 0,
            'session_started' => true,
            'status' => 'in_pool',
            'autoplay_active' => true,
            'bet_amount' => 1,
            'target_q' => $targetQ,
        ]);

        $pool = Pool::factory()->create(['base_bet' => 1, 'pool_size' => 2]);
        $user->pool_id = $pool->id;
        $user->save();

        $dummy = User::factory()->create([
            'balance' => 100,
            'battle_balance' => 10,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 10,
            'session_started' => true,
            'bet_amount' => 1,
            'status' => 'in_pool',
        ]);

        Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user->id,
            'user2_id' => $dummy->id,
            'base_bet_amount' => 1,
            'status' => 'completed',
        ]);

        return [$user, $pool];
    }

    public function test_target_q_is_compensated_by_fees_below_threshold_continues(): void
    {
        // target_q = 2.0, frais 2.5% -> seuil effectif = 2.05
        // Q = (10 + 10.2) / 10 = 2.02 < 2.05 -> session continue
        [$user, $pool] = $this->makeFoughedUser(10, 10.2, 2.0);

        $this->sessionManager->evaluatePoolEnd($pool);

        Queue::assertNotPushed(ProcessPayoutJob::class);

        $user->refresh();
        $this->assertTrue((bool) $user->session_started);
        $this->assertEquals('available', $user->status);
    }

    public function test_target_q_is_compensated_by_fees_above_threshold_pays_out(): void
    {
        // target_q = 2.0, frais 2.5% -> seuil effectif = 2.05
        // Q = (10 + 10.6) / 10 = 2.06 >= 2.05 -> payout du solde entier
        [$user, $pool] = $this->makeFoughedUser(10, 10.6, 2.0);

        $this->sessionManager->evaluatePoolEnd($pool);

        Queue::assertPushed(ProcessPayoutJob::class, function ($job) {
            return abs($job->amount - 20.6) < 0.01;
        });

        $user->refresh();
        $this->assertFalse((bool) $user->session_started);
        $this->assertEquals('stopped', $user->status);
    }
}
