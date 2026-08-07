<?php

namespace Tests\Feature\Robustness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Jobs\ProcessPayoutJob;
use App\Models\User;

class TransactionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_uses_queue_and_returns_accepted()
    {
        Queue::fake();

        $user = User::factory()->create([
            'wallet_address' => '0xQueueTestUser'
        ]);
        
        config(['app.INTERNAL_API_SECRET' => 'test_secret']);

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret'
        ])->postJson('/api/internal/payout', [
            'wallet_address' => '0xQueueTestUser',
            'amount' => 0.05
        ]);

        // It should return 202 Accepted because the job is queued
        $response->assertStatus(202);
        
        // Assert that a job was pushed to the queue
        Queue::assertPushed(ProcessPayoutJob::class, function ($job) use ($user) {
            return $job->walletAddress === '0xqueuetestuser' && $job->amount === 0.05;
        });
    }
}
