<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Pool;
use App\Models\Batch;
use App\Services\BatchProcessing\BatchCriteriaService; // Correct use statement
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\Test; // Import the Test attribute

class PoolBatchProcessingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private string $processBatchRoute = '/batch_pool_processing'; // <<< --- ADJUST THIS ROUTE ---

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('pool.size', [100, 500]);
        config()->set('pool.batch_max_size', 10);
        config()->set('pool.batch_initial_limit', 5);
        config()->set('pool.batch_max_iterations', 2);

        Cache::forget(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY);

        // Queue::fake();
        // Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===========================================
    // Test Scenarios - Using #[Test] attribute
    // ===========================================

    #[Test] // Use attribute
    public function it_returns_error_if_pool_size_config_is_missing_or_empty(): void
    {
        config()->set('pool.size', []);

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(500, $response->getStatusCode());
        // --- Assert the CORRECT error message ---
        $response->assertJson(['message' => "Configuration key 'pool.size' is empty or not defined."]);
        // -----------------------------------------
    }

    #[Test] // Use attribute
    public function it_cycles_through_pool_sizes_defined_in_config(): void
    {
        $response1 = $this->postJson($this->processBatchRoute);
        $this->assertEquals(200, $response1->getStatusCode()); // Check for potential 500 first
        $response1->assertJsonFragment(['target_pool_size' => 100]);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));

        $response2 = $this->postJson($this->processBatchRoute);
        $this->assertEquals(200, $response2->getStatusCode());
        $response2->assertJsonFragment(['target_pool_size' => 500]);
        $this->assertEquals(0, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));

        $response3 = $this->postJson($this->processBatchRoute);
        $this->assertEquals(200, $response3->getStatusCode());
        $response3->assertJsonFragment(['target_pool_size' => 100]);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

    #[Test] // Use attribute
    public function it_returns_no_work_if_no_pools_exist_for_the_target_pool_size(): void
    {
        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJson(['message' => 'No active batch or available pools for pool_size 100.', 'target_pool_size' => 100]);
        $this->assertDatabaseCount('batches', 0);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

    #[Test] // Use attribute
    public function it_creates_a_new_batch_when_pools_exist_and_no_active_batch(): void
    {
        // This test requires Batch factory to work
        $pools = Pool::factory()->count(7)->create([
            'pool_size' => 100,
            'status' => 'from_server_waitting',
        ]);
        $sortedPools = $pools->sortBy('id');

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(201, $response->getStatusCode());
        $response->assertJsonFragment(['status' => 'created']);
        $response->assertJsonPath('message', 'New batch 1 for pool_size 100 created and is waiting.');

        $this->assertDatabaseHas('batches', [
            'pool_size' => 100,
            'status' => 'waiting',
            'first_pool_id' => $sortedPools->first()->id,
            'last_pool_id' => $sortedPools->slice(0, 5)->last()->id,
            'number_of_pools' => 5,
            'max_size' => 10,
            'max_iterations' => 2,
            'iteration_count' => 0,
        ]);
         $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

    #[Test] // Use attribute
    public function it_adds_pools_to_an_existing_waiting_incomplete_batch(): void
    {
        // This test requires Batch factory to work
        $initialPools = Pool::factory()->count(5)->create([
            'pool_size' => 100,
            'status' => 'from_server_waitting',
        ]);
        // Add HasFactory trait to Batch model before this works!
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'status' => 'waiting',
            'first_pool_id' => $initialPools->sortBy('id')->first()->id,
            'last_pool_id' => $initialPools->sortBy('id')->last()->id,
            'number_of_pools' => 5,
            'max_size' => 10,
            'max_iterations' => 2,
        ]);

        $newPools = Pool::factory()->count(7)->create([
             'pool_size' => 100,
             'status' => 'from_server_waitting',
             'id' => $batch->last_pool_id + $this->faker->unique()->numberBetween(1, 50),
        ]);
        $sortedNewPools = $newPools->sortBy('id');

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(201, $response->getStatusCode());
        $response->assertJsonFragment(['status' => 'updated']);
        $response->assertJsonPath('message', "Batch {$batch->id} (pool_size 100) updated with 5 pools. Status: waiting");

        $batch->refresh();
        $this->assertEquals(10, $batch->number_of_pools);
        $this->assertEquals($sortedNewPools->slice(0, 5)->last()->id, $batch->last_pool_id);
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

    #[Test] // Use attribute
    public function it_processes_a_full_waiting_batch_and_sets_status_back_to_waiting_on_first_iteration(): void
    {
        // This test requires Batch factory to work
        $pools = Pool::factory()->count(10)->create([
            'pool_size' => 100,
            'status' => 'from_server_waitting',
        ]);
        $sortedPools = $pools->sortBy('id');
         // Add HasFactory trait to Batch model before this works!
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'status' => 'waiting',
            'first_pool_id' => $sortedPools->first()->id,
            'last_pool_id' => $sortedPools->last()->id,
            'number_of_pools' => 10,
            'max_size' => 10,
            'max_iterations' => 2,
            'iteration_count' => 0,
        ]);

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonFragment(['message' => "Batch {$batch->id} (pool_size 100) processed successfully. Final Status: waiting"]);
        $response->assertJsonFragment(['processed_count' => 10]);
        $response->assertJsonFragment(['iteration' => 1]);

        $batch->refresh();
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1, $batch->iteration_count);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

     #[Test] // Use attribute
    public function it_settles_a_batch_after_reaching_max_iterations(): void
    {
        // This test requires Batch factory to work
        $pools = Pool::factory()->count(10)->create(['pool_size' => 100]);
        $sortedPools = $pools->sortBy('id');
        // Add HasFactory trait to Batch model before this works!
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'status' => 'waiting',
            'first_pool_id' => $sortedPools->first()->id,
            'last_pool_id' => $sortedPools->last()->id,
            'number_of_pools' => 10,
            'max_size' => 10,
            'max_iterations' => 2,
            'iteration_count' => 1,
        ]);

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonFragment(['message' => "Batch {$batch->id} (pool_size 100) processed successfully. Final Status: settled"]);
        $response->assertJsonFragment(['iteration' => 2]);

        $batch->refresh();
        $this->assertEquals('settled', $batch->status);
        $this->assertEquals(2, $batch->iteration_count);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

     #[Test] // Use attribute
    public function it_handles_pool_match_exceptions_and_settles_batch_if_configured(): void
    {
        // This test requires Batch factory to work
        $pools = Pool::factory()->count(5)->create(['pool_size' => 100]);
        $sortedPools = $pools->sortBy('id');
        // Add HasFactory trait to Batch model before this works!
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'status' => 'waiting',
            'first_pool_id' => $sortedPools->first()->id,
            'last_pool_id' => $sortedPools->last()->id,
            'number_of_pools' => 5,
            'max_size' => 10,
            'max_iterations' => 2,
            'iteration_count' => 0,
        ]);

        // --- Mocking Pool::match to throw (Example using service spy) ---
        // This requires PoolProcessorService to be bound in the container
        if (app()->bound(\App\Services\BatchProcessing\PoolProcessorService::class)) {
             $mockProcessor = $this->spy(\App\Services\BatchProcessing\PoolProcessorService::class);
             // Simulate the service catching the error and returning it
             $mockProcessor->shouldReceive('processPools')
                ->once()
                ->andReturn(['processedCount' => 2, 'error' => new \Exception("Simulated pool error")]);
        } else {
             Log::warning("PoolProcessorService mocking not set up for exception test. Test may not accurately simulate exception handling.");
             // Without mocking, this test relies on a real exception happening, which is unreliable.
        }
        //--------------------------------------


        Log::spy();

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(207, $response->getStatusCode()); // Expect 207 if error is handled and some processing occurred
        $response->assertJsonFragment(['message' => "Batch {$batch->id} (pool_size 100) processed with errors. Final Status: settled"]);
        $response->assertJsonFragment(['iteration' => 0]);

        $batch->refresh();
        $this->assertEquals('settled', $batch->status);
        $this->assertEquals(0, $batch->iteration_count);

        Log::shouldHaveReceived('error')->with(Mockery::pattern('/Error processing Pool ID:|Failed to update batch status/')); // Check for either pool error or update error
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }

    #[Test] // Use attribute
    public function it_returns_no_change_if_waiting_batch_needs_pools_but_none_are_available(): void
    {
        // This test requires Batch factory to work
        $initialPools = Pool::factory()->count(5)->create([ 'pool_size' => 100, 'status' => 'from_server_waitting' ]);
         // Add HasFactory trait to Batch model before this works!
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'status' => 'waiting',
            'first_pool_id' => $initialPools->sortBy('id')->first()->id,
            'last_pool_id' => $initialPools->sortBy('id')->last()->id,
            'number_of_pools' => 5,
            'max_size' => 10,
        ]);

        $response = $this->postJson($this->processBatchRoute);

        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonFragment(['status' => 'no_change']);
        $response->assertJsonPath('message', "Batch {$batch->id} remains waiting with 5 pools, no new pools found.");

        $batch->refresh();
        $this->assertEquals(5, $batch->number_of_pools);
        $this->assertEquals($initialPools->sortBy('id')->last()->id, $batch->last_pool_id);
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
}