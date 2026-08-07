<?php

namespace Tests\Feature\Robustness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok_when_system_is_healthy()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK',
            'database' => 'OK',
            'cache' => 'OK'
        ]);
    }

    // A real health check might also check the node URL
}
