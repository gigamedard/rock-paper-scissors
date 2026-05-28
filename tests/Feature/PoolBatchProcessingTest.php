<?php
 
namespace Tests\Feature;
 
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Pool;
use App\Models\Batch;
use App\Services\BatchProcessing\BatchCriteriaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
 
class PoolBatchProcessingTest extends TestCase
{
    use RefreshDatabase, WithFaker;
 
    private string $processBatchRoute = '/api/internal/batch-processing';
 
    protected function setUp(): void
    {
        parent::setUp();
 
        config()->set('pool.size', [100, 500]);
        config()->set('pool.batch_max_size', 10);
        config()->set('pool.batch_initial_limit', 5);
        config()->set('pool.batch_max_iterations', 2);
 
        Cache::forget(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY);
        
        // Bypass internal API authentication for the route
        $this->withoutMiddleware(\App\Http\Middleware\InternalApiAuth::class);
    }
 
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
 
    #[Test]
    public function it_returns_error_if_pool_size_config_is_missing_or_empty(): void
    {
        config()->set('pool.size', []);
 
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
 
        $this->assertEquals(500, $response->getStatusCode());
        $response->assertJson(['message' => "Configuration key 'pool.size' is empty or not defined."]);
    }
 
    #[Test]
    public function it_cycles_through_pool_sizes_defined_in_config(): void
    {
        $response1 = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
        $this->assertEquals(200, $response1->getStatusCode());
        $response1->assertJsonFragment(['target_pool_size' => 100]);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
 
        $response2 = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
        $this->assertEquals(200, $response2->getStatusCode());
        $response2->assertJsonFragment(['target_pool_size' => 500]);
        $this->assertEquals(0, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
 
        $response3 = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
        $this->assertEquals(200, $response3->getStatusCode());
        $response3->assertJsonFragment(['target_pool_size' => 100]);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
 
    #[Test]
    public function it_returns_no_work_if_no_pools_exist_for_the_target_pool_size(): void
    {
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
 
        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJson(['message' => 'No active batch or available pools for pool_size 100 base_bet 1.', 'target_pool_size' => 100]);
        $this->assertDatabaseCount('batches', 0);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
 
    #[Test]
    public function it_creates_a_new_batch_when_pools_exist_and_no_active_batch(): void
    {
        $pools = Pool::factory()->count(7)->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
        ]);
        $sortedPools = $pools->sortBy('id');
 
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
 
        $this->assertEquals(201, $response->getStatusCode());
        $response->assertJsonPath('message', 'New batch 1 for pool_size 100 base_bet 1 created and is waiting.');
 
        $this->assertDatabaseHas('batches', [
            'pool_size' => 100,
            'base_bet' => 1.0,
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
 
    #[Test]
    public function it_adds_pools_to_an_existing_waiting_incomplete_batch(): void
    {
        $initialPools = Pool::factory()->count(5)->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
        ]);
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'waiting',
            'first_pool_id' => $initialPools->sortBy('id')->first()->id,
            'last_pool_id' => $initialPools->sortBy('id')->last()->id,
            'number_of_pools' => 5,
            'max_size' => 15,
            'max_iterations' => 2,
        ]);
 
        $newPools = Pool::factory()->count(7)->create([
             'pool_size' => 100,
             'base_bet' => 1.0,
             'status' => 'from_server_waitting',
         ]);
        $sortedNewPools = $newPools->sortBy('id');
 
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
        $this->assertEquals(201, $response->getStatusCode());
        $response->assertJsonPath('message', "Batch {$batch->id} (pool_size 100) updated with 7 pools. Status: waiting");
 
        $batch->refresh();
        $this->assertEquals(12, $batch->number_of_pools);
        $this->assertEquals($sortedNewPools->last()->id, $batch->last_pool_id);
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
 
    #[Test]
    public function it_processes_a_full_waiting_batch_and_sets_status_back_to_waiting_on_first_iteration(): void
    {
        $pools = Pool::factory()->count(10)->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'from_server_waitting',
        ]);
        $sortedPools = $pools->sortBy('id');
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'waiting',
            'first_pool_id' => $sortedPools->first()->id,
            'last_pool_id' => $sortedPools->last()->id,
            'number_of_pools' => 10,
            'max_size' => 10,
            'max_iterations' => 2,
            'iteration_count' => 0,
        ]);
 
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
 
        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonFragment(['message' => "Batch {$batch->id} (pool_size 100) processed successfully. Final Status: waiting"]);
        $response->assertJsonFragment(['processed_count' => 10]);
        $response->assertJsonFragment(['iteration' => 1]);
 
        $batch->refresh();
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1, $batch->iteration_count);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
 
    #[Test]
    public function it_settles_a_batch_after_reaching_max_iterations(): void
    {
        $pools = Pool::factory()->count(10)->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
        ]);
        $sortedPools = $pools->sortBy('id');
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'waiting',
            'first_pool_id' => $sortedPools->first()->id,
            'last_pool_id' => $sortedPools->last()->id,
            'number_of_pools' => 10,
            'max_size' => 10,
            'max_iterations' => 2,
            'iteration_count' => 1,
        ]);
 
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
 
        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonFragment(['message' => "Batch {$batch->id} (pool_size 100) processed successfully. Final Status: settled"]);
        $response->assertJsonFragment(['iteration' => 2]);
 
        $batch->refresh();
        $this->assertEquals('settled', $batch->status);
        $this->assertEquals(2, $batch->iteration_count);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
 
    #[Test]
    public function it_returns_no_change_if_waiting_batch_needs_pools_but_none_are_available(): void
    {
        $initialPools = Pool::factory()->count(5)->create([ 
            'pool_size' => 100, 
            'base_bet' => 1.0,
            'status' => 'from_server_waitting' 
        ]);
        $batch = Batch::factory()->create([
            'pool_size' => 100,
            'base_bet' => 1.0,
            'status' => 'waiting',
            'first_pool_id' => $initialPools->sortBy('id')->first()->id,
            'last_pool_id' => $initialPools->sortBy('id')->last()->id,
            'number_of_pools' => 5,
            'max_size' => 10,
        ]);
 
        $response = $this->postJson($this->processBatchRoute, ['base_bet' => 1.0]);
 
        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonPath('message', "Batch {$batch->id} remains waiting with 5 pools, no new pools found.");
 
        $batch->refresh();
        $this->assertEquals(5, $batch->number_of_pools);
        $this->assertEquals($initialPools->sortBy('id')->last()->id, $batch->last_pool_id);
        $this->assertEquals('waiting', $batch->status);
        $this->assertEquals(1, Cache::get(BatchCriteriaService::POOL_SIZE_INDEX_CACHE_KEY));
    }
}