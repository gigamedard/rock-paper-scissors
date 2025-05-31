<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AutoMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure game settings
        config(['game_settings.chunk_size' => 10]);
        config(['game_settings.depth_limit' => 3]);
    }

    public function testProcessAutoMatchCreatesAndResolvesFights()
    {

    // Create test users with autoplay active
    $users = User::factory()->count(4)->create([
        'autoplay_active' => true,
        'bet_amount' => 50,
        'status' => 'available',
        'balance' => 100,
    ]);

    // Create pre-moves for each user
    foreach ($users as $user) {
        DB::table('pre_moves')->insert([
            'user_id' => $user->id,
            'moves' => json_encode(['rock', 'scissors', 'paper']),
            'hashed_moves' => json_encode([
                hash('sha256', 'rock'),
                hash('sha256', 'scissors'),
                hash('sha256', 'paper'),
            ]),
            'current_index' => 0,
        ]);
    }

    // Create slice_table entry
    DB::table('slice_table')->insert([
        'instance_number' => 1,
        'bet_amount' => 50,
        'current_instance' => true,
        'last_user_id' => 0,
        'depth' => 0,
    ]);

    // Call the method to process the auto match
    $controller = new \App\Http\Controllers\AutoMatchController();
    $controller->processAutoMatch(50, 1);

    // Assert that two fights were created
    $this->assertDatabaseCount('fights', 2);

    // Fetch fights and assert balances are updated
    $fights = Fight::all();
    foreach ($fights as $fight) {
        $this->assertEquals('completed', $fight->status); // Fight completed
        $this->assertContains($fight->result, ['user1_win', 'user2_win', 'draw']);

        // Assert balance updates based on result
        $user1 = User::find($fight->user1_id);
        $user2 = User::find($fight->user2_id);

        if ($fight->result === 'user1_win') {
            $this->assertEquals(110, $user1->balance);
            $this->assertEquals(90, $user2->balance);
        } elseif ($fight->result === 'user2_win') {
            $this->assertEquals(110, $user2->balance);
            $this->assertEquals(90, $user1->balance);
        } else { // Draw
            $this->assertEquals(100, $user1->balance);
            $this->assertEquals(100, $user2->balance);
        }
    }

    // Assert the slice_table depth was incremented
    $sliceData = DB::table('slice_table')->where('instance_number', 1)->first();
    $this->assertEquals(1, $sliceData->depth);
    }

}
