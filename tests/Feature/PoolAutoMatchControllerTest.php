<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\PoolFinishedEvent;
use App\Events\SessionFinishedEvent;

class PoolAutoMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testHandlePoolEmitedEvent()
    {
        config(['app.INTERNAL_API_SECRET' => 'test_secret']);
        Event::fake();

        $user1 = User::factory()->create(['wallet_address' => '0x1234567890123456789012345678901234567890', 'balance' => 0]);
        $user2 = User::factory()->create(['wallet_address' => '0x0987654321098765432109876543210987654321', 'balance' => 0]);

        $user1->preMove()->create([
            'moves' => json_encode(['rock', 'paper', 'scissors']),
            'cid' => 'cid1',
        ]);

        $user2->preMove()->create([
            'moves' => json_encode(['paper', 'scissors', 'rock']),
            'cid' => 'cid2',
        ]);

        $payload = [
            'pool_id' => 'pool_123',
            'base_bet' => '100000000000000000', // 0.1 ETH in wei
            'users' => [$user1->wallet_address, $user2->wallet_address],
            'premove_cids' => ['cid1', 'cid2'],
            'balances' => ['100000000000000000000', '100000000000000000000'],
            'pool_salt' => 'random_salt',
            'token' => env('INNER_SCRIPT_TOKEN'),
        ];

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret',
        ])->postJson('/api/internal/handle-pool-emited', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Pool emitted Request handled successfully',
                'data' => [
                    'pool_id' => 1,
                    'status' => 'processed',
                ],
            ]);

        $this->assertDatabaseHas('pools', [
            'pool_id' => 'pool_123',
            'base_bet' => 0.1,
            'salt' => 'random_salt',
            'pool_size' => 2,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'status' => 'in_pool',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'status' => 'in_pool',
        ]);

        // Event::assertDispatched(PoolFinishedEvent::class);
        Event::assertDispatched(\App\Events\SessionFinished::class, 2);
    }
}
