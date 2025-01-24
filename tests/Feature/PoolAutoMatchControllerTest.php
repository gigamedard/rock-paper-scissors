<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Pool;
use App\Models\User;
use App\Models\Batch;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use App\Http\Controllers\PoolAutoMatchController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class PoolAutoMatchControllerTest extends TestCase
{
    use RefreshDatabase;
    use Traits\poolTrait;

    private function createTestUsers()
    {
        return [
            User::factory()->create(['wallet_address' => 'gh098hgh']),
            User::factory()->create(['wallet_address' => 'yhg7687u']),
        ];
    }

    private function mockGuzzleClient(array $responses)
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(Client::class, $client);
    }

    public function testhandlePoolEmitedEvent()
    {
        // Mock Guzzle client to return valid pre-move data
        $mock = new MockHandler([
            new Response(200, [], json_encode(['moves' => ['rock', 'paper', 'scissors']])), // Premove data for user 1
            new Response(200, [], json_encode(['moves' => ['paper', 'scissors', 'rock']])), // Premove data for user 2
        ]);
    
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
    
        // Bind the mocked client to the service container
        $this->app->instance(Client::class, $client);
    
        // Create test users
        $user1 = User::factory()->create(['wallet_address' => 'gh098hgh']);
        $user2 = User::factory()->create(['wallet_address' => 'yhg7687u']);
    
        // Call the function
    
        poolTrait::handlePoolEmitedEvent(
            'pool_123', // poolId
            100, // baseBet
            ['gh098hgh', 'yhg7687u'], // users
            ['cid1', 'cid2'], // premoveCIDs
            'random_salt' // poolSalt
        );
    
        // Assertions
        $this->assertDatabaseHas('pools', [
            'pool_id' => 'pool_123',
            'base_bet' => 100,
            'salt' => 'random_salt',
            'pool_size' => 2,
        ]);
    
        $this->assertDatabaseHas('pre_moves', [
            'user_id' => $user1->id,
            'moves' => json_encode(['rock', 'paper', 'scissors']),
        ]);
    
        $this->assertDatabaseHas('pre_moves', [
            'user_id' => $user2->id,
            'moves' => json_encode(['paper', 'scissors', 'rock']),
        ]);
    
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'status' => 'in_pool',
        ]);
    
        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'status' => 'in_pool',
        ]);
    
        $pool = Pool::where('pool_id', 'pool_123')->first();
        $this->assertCount(2, $pool->users);
    }

    public function testhandlePoolEmitedEventWithInvalidInput()
    {
        $controller = new PoolAutoMatchController();
        $this->expectException(\InvalidArgumentException::class);
        $controller->handlePoolEmitedEvent('', 100, [], [], '');
    }

    public function testhandlePoolEmitedEventWithFailedApiRequest()
    {
        // Mock a failed API request
        $this->mockGuzzleClient([new Response(500)]);

        // Call the method and expect an exception
        $controller = new PoolAutoMatchController();
        $this->expectException(\RuntimeException::class);
        $controller->handlePoolEmitedEvent('pool_123', 100, ['gh098hgh', 'yhg7687u'], ['cid1', 'cid2'], 'random_salt');
    }

    public function testhandlePoolEmitedEventWithCidMismatch()
    {
        // Mock Guzzle client to return valid pre-move data
        $mock = new MockHandler([
            new Response(200, [], json_encode(['moves' => ['rock', 'paper', 'scissors']])), // Premove data for user 1
            new Response(200, [], json_encode(['moves' => ['paper', 'scissors', 'rock']])), // Premove data for user 2
        ]);
    
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
    
        // Bind the mocked client to the service container
        $this->app->instance(Client::class, $client);
    
        // Create test users
        $user1 = User::factory()->create(['wallet_address' => 'gh098hgh']);
        $user2 = User::factory()->create(['wallet_address' => 'yhg7687u']);
    
        // Simulate a CID mismatch by setting a different CID in the user's pre-move
        $user1->preMove()->create([
            'moves' => json_encode(['rock', 'paper', 'scissors']),
            'hashed_moves' => json_encode(['hash1', 'hash2', 'hash3']),
            'nonce' => 'nonce1',
            'current_index' => 0,
            'cid' => 'different_cid', // Simulate CID mismatch
        ]);
    
        // Call the function
        $controller = new PoolAutoMatchController();
        $controller->handlePoolEmitedEvent(
            'pool_123', // poolId
            100, // baseBet
            ['gh098hgh', 'yhg7687u'], // users
            ['cid1', 'cid2'], // premoveCIDs
            'random_salt' // poolSalt
        );
    
        // Assertions
        $this->assertDatabaseHas('pools', [
            'pool_id' => 'pool_123',
            'base_bet' => 100,
            'salt' => 'random_salt',
            'pool_size' => 2,
        ]);
    
        // Ensure the user with CID mismatch is not updated
        $this->assertDatabaseHas('pre_moves', [
            'user_id' => $user1->id,
            'moves' => json_encode(['rock', 'paper', 'scissors']),
            'cid' => 'different_cid',
        ]);
    
        // Ensure the other user is updated correctly
        $this->assertDatabaseHas('pre_moves', [
            'user_id' => $user2->id,
            'moves' => json_encode(['paper', 'scissors', 'rock']),
        ]);
    
        // Ensure the user with CID mismatch is not marked as in_pool
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'status' => 'available', // Status should remain unchanged
        ]);
    
        // Ensure the other user is marked as in_pool
        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'status' => 'in_pool',
        ]);
    }
}

