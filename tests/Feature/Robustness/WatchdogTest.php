<?php

namespace Tests\Feature\Robustness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use App\Console\Commands\WatchdogCheck;

class WatchdogTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_route_updates_cache()
    {
        config(['app.INTERNAL_API_SECRET' => 'test_secret']);

        $response = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret'
        ])->postJson('/api/internal/ping');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertTrue(Cache::has('bridge_last_ping'));
    }

    public function test_watchdog_command_alerts_when_ping_is_old()
    {
        // Set ping to 30 seconds ago
        Cache::put('bridge_last_ping', now()->subSeconds(30)->timestamp);

        // We expect a log error
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function($message) {
                return str_contains($message, 'CRITICAL: Node.js Bridge is down!');
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->artisan('watchdog:check')->assertFailed();
    }

    public function test_watchdog_command_is_silent_when_ping_is_recent()
    {
        // Set ping to 2 seconds ago
        Cache::put('bridge_last_ping', now()->subSeconds(2)->timestamp);

        Log::shouldReceive('error')->never();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->artisan('watchdog:check')->assertSuccessful();
    }
}
