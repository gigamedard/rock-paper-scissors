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
            ->andReturn(new JsonResponse(['message' => 'Batch processed'], 200));

        $this->app->instance(BatchProcessingService::class, $mockService);

        // Create a user for internal auth if needed, or bypass middleware if possible.
        // The route is protected by 'auth.internal'. We need to see how to pass this.
        // Looking at InternalApiAuth middleware might be needed, but for now let's try acting as a user 
        // or mocking the middleware.
        
        // Assuming InternalApiAuth checks for a specific token or user capability.
        // Let's try to mock the middleware or just assert 401 if we don't provide auth, 
        // then try to provide auth.
        
        // For this test, we'll mock the middleware to allow the request.
        $this->withoutMiddleware(\App\Http\Middleware\InternalApiAuth::class);

        $response = $this->getJson('/api/internal/batch-processing');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Batch processed']);
    }

    public function test_console_command_triggers_batch_processing()
    {
        // Mock the BatchProcessingService
        $mockService = Mockery::mock(BatchProcessingService::class);
        $mockService->shouldReceive('processBatch')
            ->once()
            ->andReturn(new JsonResponse(['message' => 'Batch processed via command'], 200));

        $this->app->instance(BatchProcessingService::class, $mockService);

        $this->artisan('batch:process')
            ->expectsOutput('Starting batch processing...')
            ->expectsOutput('Batch processing completed successfully. Status: 200')
            ->assertExitCode(0);
    }
}
