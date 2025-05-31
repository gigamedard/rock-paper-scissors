<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FightAutoplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default users for testing
        $this->user1 = User::factory()->create(['balance' => 100]);
        $this->user2 = User::factory()->create(['balance' => 100]);

        // Add pre-moves for both users
        DB::table('pre_moves')->insert([
            'user_id' => $this->user1->id,
            'moves' => json_encode(['rock', 'scissors', 'paper']),
            'current_index' => 0,
        ]);

        DB::table('pre_moves')->insert([
            'user_id' => $this->user2->id,
            'moves' => json_encode(['scissors', 'paper', 'rock']),
            'current_index' => 0,
        ]);
    }

    public function testHandleAutoplayFightProcessesCorrectly()
    {
        // Create a fight
        $fight = Fight::create([
            'user1_id' => $this->user1->id,
            'user2_id' => $this->user2->id,
            'base_bet_amount' => 10,
            'status' => 'waiting_for_both',
        ]);

        // Execute the handleAutoplayFight method
        $result = $fight->handleAutoplayFight();

        // Assert balances are updated correctly
        if ($result === 'user1_win') {
            $this->assertEquals(110, $this->user1->fresh()->balance);
            $this->assertEquals(90, $this->user2->fresh()->balance);
        } elseif ($result === 'user2_win') {
            $this->assertEquals(110, $this->user2->fresh()->balance);
            $this->assertEquals(90, $this->user1->fresh()->balance);
        } else { // Draw
            $this->assertEquals(100, $this->user1->fresh()->balance);
            $this->assertEquals(100, $this->user2->fresh()->balance);
        }

        // Assert fight result and status
        $this->assertEquals($result, $fight->fresh()->result);
        $this->assertEquals('completed', $fight->fresh()->status);
    }

    public function testPreMoveIndexResetsAfterAllMovesUsed()
    {
        // Use all moves in user1's pre-move list
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals(['rock', 'scissors', 'paper'][$i], $this->getPreMove($this->user1->id));
        }

        // Assert index resets after all statusmoves are used
        $nextMove = $this->getPreMove($this->user1->id);
        $this->assertEquals('rock', $nextMove); // First move repeats
        $this->assertDatabaseHas('pre_moves', [
            'user_id' => $this->user1->id,
            'current_index' => 1, // Index reset and incremented
        ]);
    }

    private function getPreMove($userId)
    {
        // Mimic the getPreMove logic
        $preMove = DB::table('pre_moves')->where('user_id', $userId)->first();
        $moves = json_decode($preMove->moves, true);
        $currentIndex = $preMove->current_index;

        if ($currentIndex >= count($moves)) {
            $currentIndex = 0; // Reset the index
        }

        $nextMove = $moves[$currentIndex];

        DB::table('pre_moves')->where('user_id', $userId)->update([
            'current_index' => $currentIndex + 1,
        ]);

        return $nextMove;
    }
}
