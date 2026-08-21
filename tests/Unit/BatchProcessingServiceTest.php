<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BatchProcessingService;
use App\Services\BatchProcessing\BatchCriteriaService;
use App\Services\BatchProcessing\BatchFinderService;
use App\Services\BatchProcessing\PoolFetcherService;
use App\Services\BatchProcessing\BatchManagerService;
use App\Services\BatchProcessing\PoolProcessorService;
use App\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class BatchProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $batchCriteriaMock;
    protected $batchFinderMock;
    protected $poolFetcherMock;
    protected $batchManagerMock;
    protected $poolProcessorMock;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchCriteriaMock = Mockery::mock(BatchCriteriaService::class);
        $this->batchFinderMock = Mockery::mock(BatchFinderService::class);
        $this->poolFetcherMock = Mockery::mock(PoolFetcherService::class);
        $this->batchManagerMock = Mockery::mock(BatchManagerService::class);
        $this->poolProcessorMock = Mockery::mock(PoolProcessorService::class);

        $this->service = new BatchProcessingService(
            $this->batchCriteriaMock,
            $this->batchFinderMock,
            $this->poolFetcherMock,
            $this->batchManagerMock,
            $this->poolProcessorMock
        );
    }

    public function test_process_batch_returns_error_if_criteria_fails()
    {
        $this->batchCriteriaMock->shouldReceive('getTargetPoolSize')
            ->once()->andReturn(['targetPoolSize' => null, 'error' => 'Config error']);

        $result = $this->service->processBatch(1.0);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Config error', $result['message']);
        $this->assertEquals(500, $result['http_code']);
    }

    public function test_process_batch_returns_no_work_if_no_batch_and_no_pools()
    {
        $this->batchCriteriaMock->shouldReceive('getTargetPoolSize')->andReturn(['targetPoolSize' => 5, 'error' => null]);
        
        // Mock Finder to return null (no active batch)
        $this->batchFinderMock->shouldReceive('findActiveBatchWithLock')
            ->with(5, 1.0)
            ->once()->andReturn(null);

        // Mock Fetcher to say no pools exist
        $this->poolFetcherMock->shouldReceive('processablePoolsExist')
            ->with(5, 1.0)
            ->once()->andReturn(false);

        $result = $this->service->processBatch(1.0);

        $this->assertEquals('no_work', $result['status']);
        $this->assertEquals(200, $result['http_code']);
    }

    public function test_process_batch_creates_new_batch_if_pools_exist()
    {
        $this->batchCriteriaMock->shouldReceive('getTargetPoolSize')->andReturn(['targetPoolSize' => 5, 'error' => null]);
        
        $this->batchFinderMock->shouldReceive('findActiveBatchWithLock')->andReturn(null);
        
        $this->poolFetcherMock->shouldReceive('processablePoolsExist')
            ->with(5, 1.0)
            ->andReturn(true);
            
        $this->poolFetcherMock->shouldReceive('fetchInitialPools')
            ->with(5, 1.0, Mockery::any()) 
            ->andReturn(collect([new \App\Models\Pool(['id' => 1])]));

        $mockBatch = new Batch(['id' => 123, 'status' => 'waiting']);
        $this->batchManagerMock->shouldReceive('createBatch')
            ->with(5, 1.0, Mockery::type('Illuminate\Support\Collection'))
            ->once()->andReturn($mockBatch);

        $result = $this->service->processBatch(1.0);

        $this->assertEquals('created', $result['status']);
        $this->assertStringContainsString('New batch 123', $result['message']);
        $this->assertEquals(201, $result['http_code']);
    }
}
