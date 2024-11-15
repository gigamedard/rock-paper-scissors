<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Fight;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AutoMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set game settings in config
        config(['game_settings.depth_limit' => 3]);
        config(['game_settings.chunk_size' => 10]);
        config(['game_settings.bet_amounts' => [50, 100, 200]]);
    }

    public function testRouteExecutesControllerMethod()
    {
        $response = $this->get('/triggermatching');

        $response->assertOk(); // Verify the route executes
    }

    public function testProcessAutoMatchCreatesFightsAndUpdatesSliceTable()
    {
        // Seed test data
        $instanceNumber = 1;
        DB::table('slice_table')->insert([
            'instance_number' => $instanceNumber,
            'bet_amount' => 50,
            'current_instance' => true,
            'depth' => 0,
            'last_user_id' => 0,
            'ultime_user_id' => 0,
            'updated_at' => now(),
        ]);

        // Create 6 users with autoplay active and the same bet amount
        User::factory()->count(6)->create([
            'autoplay_active' => true,
            'bet_amount' => 50,
            'status' => 'available',
        ]);

        // Call the method
        $controller = new \App\Http\Controllers\AutoMatchController();
        $controller->processAutoMatch(50, $instanceNumber);

        // Assert fights are created
        $this->assertDatabaseCount('fights', 3);

        // Assert slice_table is updated
        $this->assertDatabaseHas('slice_table', [
            'instance_number' => $instanceNumber,
            'depth' => 1, // Depth incremented
        ]);
    }

    public function testDepthUpdateAndInstanceSwitching()
    {
        // Seed two slice_table entries
        DB::table('slice_table')->insert([
            'instance_number' => 1,
            'bet_amount' => 50,
            'current_instance' => true,
            'depth' => 3, // Already at the depth limit
            'updated_at' => now(),
        ]);

        DB::table('slice_table')->insert([
            'instance_number' => 2,
            'bet_amount' => 50,
            'current_instance' => false,
            'depth' => 0,
            'updated_at' => now()->subMinute(),
        ]);

        // Call the method
        $controller = new \App\Http\Controllers\AutoMatchController();
        $controller->selectSliceInstence(50);

        // Assert current instance was switched
        $this->assertDatabaseHas('slice_table', [
            'instance_number' => 1,
            'current_instance' => false,
        ]);

        $this->assertDatabaseHas('slice_table', [
            'instance_number' => 2,
            'current_instance' => true,
            'depth' => 0, // Depth reset
        ]);
    }

    public function testSelectSliceInstanceForAllBetAmount()
    {
        // Seed slice_table for multiple bet amounts
        foreach ([1, 2, 4,8,16] as $betAmount) {
            DB::table('slice_table')->insert([
                'instance_number' => 1,
                'bet_amount' => $betAmount,
                'current_instance' => true,
                'depth' => 0,
                'updated_at' => now(),
            ]);
        }

        // Call the method
        $controller = new \App\Http\Controllers\AutoMatchController();
        $controller->selectSliceInstenceForAllBetAmount();

        // Assert no errors occurred and logic processed for all bet amounts
        $this->assertTrue(true);
    }



}
