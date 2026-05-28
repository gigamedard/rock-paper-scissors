<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCard;
use App\Models\Card;
use App\Helpers\Web3Helper;
use App\Jobs\SyncUserLimitsJob;
use App\Jobs\ProcessPayoutJob;
use App\Jobs\SetCooldownJob;
use App\Jobs\ReconstructPoolJob;
use App\Jobs\ProcessEscrowJob;
use App\Jobs\CreateOfferJob;
use App\Jobs\VerifyCardPurchaseJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class BlockchainJobsTest extends TestCase
{
    use RefreshDatabase;

    protected $web3Mock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->web3Mock = Mockery::mock(Web3Helper::class);
        $this->app->instance(Web3Helper::class, $this->web3Mock);
        
        Http::fake([
            '*' => Http::response(['success' => true], 200)
        ]);
    }

    public function test_sync_user_limits_job_calls_web3_helper()
    {
        $user = User::factory()->create([
            'wallet_address' => '0xUserLimit',
            'bet_amount' => 0.01
        ]);

        $this->web3Mock->shouldReceive('setUserLimits')
            ->once()
            ->with(Mockery::any(), '0xUserLimit', 0.01, 2.0, 86380, 0)
            ->andReturn(['success' => true]);

        $job = new SyncUserLimitsJob($user);
        $job->handle();
        
        $this->assertTrue(true);
    }


    public function test_process_payout_job_calls_web3_helper()
    {
        $this->web3Mock->shouldReceive('sendPayement')
            ->once()
            ->with(Mockery::any(), '0xWalletAddress', 1.5)
            ->andReturn(['success' => true]);

        $job = new ProcessPayoutJob('0xWalletAddress', 1.5);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_set_cooldown_job_calls_web3_helper()
    {
        $this->web3Mock->shouldReceive('setUserNextSessionTime')
            ->once()
            ->with(Mockery::any(), '0xWalletAddress', 1600000000)
            ->andReturn(['success' => true]);

        $job = new SetCooldownJob('0xWalletAddress', 1600000000);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_reconstruct_pool_job_calls_web3_helper_refund_and_invalidate()
    {
        $invalidAddresses = ['0xInvalid1', '0xInvalid2'];
        
        $this->web3Mock->shouldReceive('refundUsers')
            ->once()
            ->with(Mockery::any(), $invalidAddresses);

        $this->web3Mock->shouldReceive('invalidatePoolUsers')
            ->once()
            ->with(Mockery::any(), 0.05, $invalidAddresses);

        $job = new ReconstructPoolJob($invalidAddresses, 0.05);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_reconstruct_pool_job_calls_web3_helper_validate_when_no_invalid()
    {
        $this->web3Mock->shouldReceive('validatePool')
            ->once()
            ->with(Mockery::any(), 0.05);

        $job = new ReconstructPoolJob([], 0.05);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_create_offer_job_sends_http_post()
    {
        $offerParams = [
            'sellerAddress' => '0xSeller',
            'sntAmount' => 10,
            'avaxAmount' => 0.5,
            'durationHours' => 24
        ];

        $job = new CreateOfferJob($offerParams);
        $job->handle();

        Http::assertSent(function ($request) use ($offerParams) {
            return str_contains($request->url(), '/create-offer') &&
                   $request['sellerAddress'] === '0xSeller' &&
                   $request['sntAmount'] == 10;
        });
    }

    public function test_process_escrow_job_sends_http_post()
    {
        $job = new ProcessEscrowJob('create-trade', ['foo' => 'bar']);
        $job->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/escrow/create-trade') &&
                   $request['foo'] === 'bar';
        });
    }

    public function test_verify_card_purchase_job_success_activates_card()
    {
        $user = User::factory()->create(['wallet_address' => '0xBuyer']);
        $card = Card::create([
            'name' => 'Test Card',
            'price' => 10,
            'effect_type' => 'base_bet_modifier',
            'effect_value' => 0.05,
            'duration_type' => 'sessions',
            'duration_value' => 3,
            'is_active' => true
        ]);
        $userCard = UserCard::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'status' => 'pending',
            'tx_hash' => '0xPurchaseTx'
        ]);

        $this->web3Mock->shouldReceive('verifySntTransfer')
            ->once()
            ->with(Mockery::any(), '0xPurchaseTx', 10, '0xBuyer')
            ->andReturn(['success' => true]);

        $this->web3Mock->shouldReceive('setUserLimits')->andReturn(['success' => true]);

        $job = new VerifyCardPurchaseJob($userCard);
        $job->handle();

        $this->assertEquals('available', $userCard->fresh()->status);
    }
}
