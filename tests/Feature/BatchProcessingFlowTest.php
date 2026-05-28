<?php
 
namespace Tests\Feature;
 
use App\Models\Batch;
use App\Models\Pool;
use App\Services\BatchProcessingService;
use App\Services\PoolMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
 
class BatchProcessingFlowTest extends TestCase
{
    use RefreshDatabase;
 
    protected function setUp(): void
    {
        parent::setUp();
        // Mock the PoolMatchingService to avoid actual game logic
        $this->mock(PoolMatchingService::class, function ($mock) {
            $mock->shouldReceive('match')->andReturn(null);
        });
    }
 
    public function test_it_creates_a_new_batch_when_pools_are_available()
    {
        // Arrange
        Config::set('pool.size', [5]);
        Config::set('pool.batch_max_size', 3);
        
        // Create pools with the status expected by the service
        Pool::factory()->count(2)->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting', // Using the typo as found in code
        ]);
 
        // Act
        $service = app(BatchProcessingService::class);
        $response = $service->processBatch(1.0);
 
        // Assert
        $this->assertEquals(201, $response['http_code'] ?? null);
        $this->assertDatabaseCount('batches', 1);
        
        $batch = Batch::first();
        $this->assertEquals(5, $batch->pool_size);
        $this->assertEquals(2, $batch->number_of_pools);
        $this->assertEquals('waiting', $batch->status);
        
        // Verify pools are updated
        $this->assertEquals(2, Pool::where('status', 'batched')->count());
    }
 
    public function test_it_fills_an_existing_waiting_batch()
    {
        // Arrange
        Config::set('pool.size', [5]);
        Config::set('pool.batch_max_size', 10); // Large enough to not be full yet
 
        // Create an existing batch
        $batch = Batch::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'waiting',
            'number_of_pools' => 2,
            'max_size' => 10,
            'last_pool_id' => 100, // Assume previous pools were up to 100
        ]);
 
        // Create new pools that should be picked up
        Pool::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
            'id' => 101,
        ]);
        Pool::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
            'id' => 102,
        ]);
        Pool::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
            'id' => 103,
        ]);
        
        // These are just extra to ensure we have enough
        Pool::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
            'id' => 104,
        ]);
        Pool::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
            'id' => 105,
        ]);
 
        // Act
        $service = app(BatchProcessingService::class);
        $response = $service->processBatch(1.0);
 
        // Assert
        $this->assertEquals(201, $response['http_code'] ?? null);
        
        $batch->refresh();
        $this->assertEquals(7, $batch->number_of_pools); // 2 existing + 5 new
        $this->assertEquals(105, $batch->last_pool_id);
    }
 
    public function test_it_processes_a_full_batch()
    {
        // Arrange
        Config::set('pool.size', [5]);
        Config::set('pool.batch_max_size', 3);
 
        // Create a batch that is full (or ready to be full)
        // Actually the logic is: if waiting & pools >= max_size, then process.
        
        $batch = Batch::factory()->create([
            'pool_size' => 5,
            'base_bet' => 1.0,
            'status' => 'waiting',
            'number_of_pools' => 3,
            'max_size' => 3,
            'first_pool_id' => 1,
            'last_pool_id' => 3,
            'iteration_count' => 0,
            'max_iterations' => 5,
        ]);
 
        // Create the pools for this batch
        Pool::factory()->create(['id' => 1, 'pool_size' => 5, 'base_bet' => 1.0, 'status' => 'batched']);
        Pool::factory()->create(['id' => 2, 'pool_size' => 5, 'base_bet' => 1.0, 'status' => 'batched']);
        Pool::factory()->create(['id' => 3, 'pool_size' => 5, 'base_bet' => 1.0, 'status' => 'batched']);
 
        // Act
        $service = app(BatchProcessingService::class);
        $response = $service->processBatch(1.0);
 
        // Assert
        $this->assertEquals(200, $response['http_code'] ?? null);
        
        $batch->refresh();
        // It should have incremented iteration count
        $this->assertEquals(1, $batch->iteration_count);
        // Status should be waiting (since max_iterations is 5)
        $this->assertEquals('waiting', $batch->status);
    }
}
