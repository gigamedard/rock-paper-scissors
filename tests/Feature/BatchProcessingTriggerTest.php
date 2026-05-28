<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BatchProcessingService;
use Illuminate\Http\JsonResponse;
use Mockery;
use App\Models\User;

class BatchProcessingTriggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_http_route_triggers_batch_processing()
    {
        // Mock the BatchProcessingService
        $mockService = Mockery::mock(BatchProcessingService::class);
        $mockService->shouldReceive('processBatch')
            ->once()
            ->with(1.0)
            ->andReturn(['message' => 'Batch processed', 'http_code' => 200]);

        $this->app->instance(BatchProcessingService::class, $mockService);

        $this->withoutMiddleware(\App\Http\Middleware\InternalApiAuth::class);

        $response = $this->postJson('/api/internal/batch-processing', ['base_bet' => 1.0]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Batch processed']);
    }

    public function test_console_command_triggers_batch_processing()
    {
        // Mock the BatchProcessingService
        $mockService = Mockery::mock(BatchProcessingService::class);
        $mockService->shouldReceive('processAllBetTiers')
            ->once()
            ->andReturn([
                'status' => 'success',
                'message' => 'Batch processed via command',
                'http_code' => 200,
                'current_tier' => 1.0,
                'processed_count' => 1
            ]);

        $this->app->instance(BatchProcessingService::class, $mockService);

        $this->artisan('batch:process')
            ->expectsOutput('Starting batch processing...')
            ->expectsOutput('Batch processing completed. Status: success')
            ->expectsOutput('Message: Batch processed via command')
            ->expectsOutput('Processed Count: 1')
            ->expectsOutput('Tier processed: 1')
            ->assertExitCode(0);
    }
}
