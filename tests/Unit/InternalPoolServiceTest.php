<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pool;
use App\Services\InternalPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class InternalPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $mockNotification = $this->createMock(\App\Services\NotificationService::class);
        $this->service = new InternalPoolService($mockNotification);
        Config::set('pool.size', [5]); // Set pool size to 5 for testing
    }

    public function test_it_does_not_create_pool_if_users_are_insufficient()
    {
        // Create 4 users (need 5)
        User::factory()->count(4)->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);

        $result = $this->service->processInternalPools(1.0);

        $this->assertEquals(0, $result['created_pools']);
        $this->assertEquals(0, Pool::count());
    }

    public function test_it_creates_pool_when_exact_number_of_users_exist()
    {
        $users = User::factory()->count(5)->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);

        $result = $this->service->processInternalPools(1.0);

        $this->assertEquals(1, $result['created_pools']);
        $this->assertEquals(5, $result['users_processed']);
        $this->assertEquals(1, Pool::count());

        $pool = Pool::first();
        $this->assertEquals(1.0, $pool->base_bet);
        $this->assertEquals('from_server_waitting', $pool->status);

        // Verify users updated
        foreach ($users as $user) {
            $user->refresh();
            $this->assertEquals('in_pool', $user->status);
            $this->assertEquals($pool->id, $user->pool_id);
            $this->assertTrue((bool)$user->session_started);
        }
    }

    public function test_it_creates_multiple_pools_and_ignores_remainder()
    {
        // Create 12 users (should make 2 pools of 5, leave 2)
        User::factory()->count(12)->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);

        $result = $this->service->processInternalPools(1.0);

        $this->assertEquals(2, $result['created_pools']);
        $this->assertEquals(10, $result['users_processed']); // 2 * 5
        $this->assertEquals(2, Pool::count());

        $remainingUsers = User::where('status', 'available')->count();
        $this->assertEquals(2, $remainingUsers);
    }

    public function test_it_ignores_users_with_different_base_bet()
    {
        // 5 users with 1.0
        User::factory()->count(5)->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);
        // 5 users with 2.0
        User::factory()->count(5)->create([
            'status' => 'available',
            'bet_amount' => 2.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);

        // Process for 1.0
        $result = $this->service->processInternalPools(1.0);

        $this->assertEquals(1, $result['created_pools']);
        $this->assertEquals(5, $result['users_processed']);
        
        // Verify only 1.0 pool created
        $pool = Pool::first();
        $this->assertEquals(1.0, $pool->base_bet);
    }

    public function test_it_ignores_users_not_available_or_autoplay_disabled()
    {
        // 3 valid users
        User::factory()->count(3)->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);
        // 1 user busy
        User::factory()->create([
            'status' => 'in_pool',
            'bet_amount' => 1.0,
            'autoplay_active' => true,
            'balance' => 10.0,
        ]);
        // 1 user autoplay off
        User::factory()->create([
            'status' => 'available',
            'bet_amount' => 1.0,
            'autoplay_active' => false,
            'balance' => 10.0,
        ]);

        // Total 5 users match bet amount, but only 3 are valid candidates
        $result = $this->service->processInternalPools(1.0);

        $this->assertEquals(0, $result['created_pools']);
    }
}
