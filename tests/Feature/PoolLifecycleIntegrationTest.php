<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pool;
use App\Models\Batch;
use App\Models\Fight;
use App\Services\InternalPoolService;
use App\Services\BatchProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PoolLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $internalPoolService;
    protected $batchProcessingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake all HTTP calls to Node.js server to avoid external dependencies during tests
        \Illuminate\Support\Facades\Http::fake([
            '*/setUserLimits' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*/setUserNextSessionTime' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*/sendPayment' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*/getUserNonce/*' => \Illuminate\Support\Facades\Http::response(['nonce' => 0], 200),
            '*/get-game-config' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 'success'], 200),
        ]);
        
        // Configure pool settings for test
        Config::set('pool.size', [2]); // Small pool size for easy testing
        Config::set('pool.batch_max_size', 5);
        Config::set('pool.batch_initial_limit', 50);
        Config::set('pool.base_bet', [1.0]);
        // Mock ABI for Web3Helper if referenced
        Config::set('game_settings.abi', []); 

        // Mock Web3Helper to avoid network calls
        $web3Mock = \Mockery::mock(\App\Helpers\Web3Helper::class)->shouldIgnoreMissing();
        $web3Mock->shouldReceive('premoveExists')->andReturn(true)->byDefault();
        $web3Mock->shouldReceive('sortAddressesWithSalt')->andReturnUsing(function($addrs, $salt) { 
            sort($addrs); return $addrs; 
        })->byDefault();
        $web3Mock->shouldReceive('setUserLimits')->andReturn(['status' => 'success'])->byDefault();
        $web3Mock->shouldReceive('setUserNextSessionTime')->andReturn(['status' => 'success'])->byDefault();
        $web3Mock->shouldReceive('sendPayement')->andReturn(['status' => 'success'])->byDefault();
        $this->app->instance(\App\Helpers\Web3Helper::class, $web3Mock);

        // Mock PinataService
        $pinataMock = \Mockery::mock(\App\Services\PinataService::class);
        $pinataMock->shouldReceive('pinJsonData')->andReturn('QmTestCID');
        $this->app->instance(\App\Services\PinataService::class, $pinataMock);

        $this->internalPoolService = app(InternalPoolService::class);
        $this->batchProcessingService = app(BatchProcessingService::class);
    }

    public function test_full_lifecycle_flow()
    {
        // 1. Setup Users with unique wallet addresses
        $users = collect();
        for ($i = 0; $i < 4; $i++) {
            $users->push(User::factory()->create([
                'status' => 'available',
                'bet_amount' => 1.0,
                'autoplay_active' => true,
                'balance' => 10.0,
                'wallet_address' => '0x' . \Illuminate\Support\Str::random(40),
            ]));
        }
        
        // Fix PreMove relation if Factory doesn't handle it
        foreach($users as $index => $user) {
            $moves = ($index % 2 === 0) ? ['rock', 'rock', 'rock'] : ['scissors', 'scissors', 'scissors'];
            DB::table('pre_moves')->insert([
                'user_id' => $user->id,
                'moves' => json_encode($moves),
                'current_index' => 0,
                'cid' => 'QmTest',
                'session_first_pool_id' => 0
            ]);
        }

        // 2. Run Internal Pool Service
        $result = $this->internalPoolService->processInternalPools(1.0);
        
        $this->assertEquals(2, $result['created_pools']);
        $this->assertEquals(2, Pool::count());
        $this->assertEquals(4, User::where('status', 'in_pool')->count());

        // 3. Run Batch Processing (Creation)
        $batchResult = $this->batchProcessingService->processBatch(1.0);
        
        $this->assertEquals('created', $batchResult['status']);
        $this->assertEquals(1, Batch::count());
        $batch = Batch::first();
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1.0, $batch->base_bet);

        // 4. Run Batch Processing (Transition to Running because max_size not met? No, max 5, current 2 pools)
        // We set max_size to 5, we have 2 pools.
        // Logic: if waiting & < max_size -> load more.
        // We want to force it to run.
        
        // Let's optimize: Update batch to be full or force status
        $batch->max_size = 2;
        $batch->save();

        // 5. Run Batch Processing (Should switch to running and process)
        $processResult = $this->batchProcessingService->processBatch(1.0);
        
        // It might first detect "waiting & full" -> 'processing_deferred' -> process
        if ($processResult['status'] === 'processing_deferred') {
            // Success
        } else {
             // Maybe it just updated status to running?
             // Let's run again if needed.
             if ($processResult['status'] === 'no_action' && $batch->fresh()->status === 'running') {
                 $processResult = $this->batchProcessingService->processBatch(1.0);
             }
        }
        
        $this->assertEquals('success', $processResult['status']); // Updated to array return
        
        // 6. Verify Fights
        $fights = Fight::all();
        $this->assertGreaterThan(0, $fights->count());
        
        // Each pool (size 2) should have 1 fight
        $this->assertEquals(2, $fights->count());

        foreach ($fights as $fight) {
            $this->assertEquals('completed', $fight->status);
        }
    }
}
