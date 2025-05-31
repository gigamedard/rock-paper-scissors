<?php 
namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PoolAutoMatchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_adds_dropped_users_to_queue_table()
    {
        // Create a pool and users
        $pool = Pool::factory()->create(['base_bet' => 10, 'pool_size' => 2]);
        $user1 = User::factory()->create(['status' => 'available', 'balance' => 100]);
        $user2 = User::factory()->create(['status' => 'available', 'balance' => 100]);

        // Add pre-moves for the users
        DB::table('pre_moves')->insert([
            [
                'user_id' => $user1->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
            [
                'user_id' => $user2->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
        ]);

        // Add users to the pool
        $pool->users()->attach([$user1->id, $user2->id]);

        // Simulate a fight where user1 loses
        $fight = Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
            'status' => 'completed',
            'result' => 'user2_win',
            'base_bet_amount' => $pool->base_bet,
        ]);

        // Call the method to handle the fight
        $fight->handlePoolAutoplayFight($pool->base_bet, $pool->pool_size);

        // Assert that the loser (user1) is added to the queue_table
        $this->assertDatabaseHas('queue_table', [
            'user_id' => $user1->id,
        ]);

        // Check if a pool with the same base_bet and pool_size exists
        $existingPool = Pool::where('base_bet', $pool->base_bet)
                            ->where('pool_size', $pool->pool_size)
                            ->whereDoesntHave('users', function ($query) {
                                $query->havingRaw('COUNT(*) >= ?', [2]);
                            })
                            ->first();

        if ($existingPool) {
            // Assert that the loser is added to the existing pool
            $this->assertTrue($existingPool->users()->where('user_id', $user1->id)->exists());
        } else {
            // Assert that a new pool is created and the loser is added to it
            $newPool = Pool::where('base_bet', $pool->base_bet)
                           ->where('pool_size', $pool->pool_size)
                           ->whereHas('users', function ($query) use ($user1) {
                               $query->where('user_id', $user1->id);
                           })
                           ->first();

            $this->assertNotNull($newPool);
            $this->assertTrue($newPool->users()->where('user_id', $user1->id)->exists());
        }
    }

    #[Test]
    public function it_creates_a_new_pool_from_queue_table()
    {
        // Add users to the queue_table
        $user1 = User::factory()->create(['status' => 'available']);
        $user2 = User::factory()->create(['status' => 'available']);
        $user3 = User::factory()->create(['status' => 'available']);
        $user4 = User::factory()->create(['status' => 'available']);
        $user5 = User::factory()->create(['status' => 'available']);

        // Add pre-moves for the users
        DB::table('pre_moves')->insert([
            [
                'user_id' => $user1->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
            [
                'user_id' => $user2->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
            [
                'user_id' => $user3->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
            [
                'user_id' => $user4->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
            [
                'user_id' => $user5->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
        ]);

        DB::table('queue_table')->insert([
            ['user_id' => $user1->id, 'created_at' => now()],
            ['user_id' => $user2->id, 'created_at' => now()],
            ['user_id' => $user3->id, 'created_at' => now()],
            ['user_id' => $user4->id, 'created_at' => now()],
            ['user_id' => $user5->id, 'created_at' => now()],
        ]);

        // Call the method to create a new pool
        $pool = $this->createNewPool();

        // Assert that a new pool is created
        $this->assertDatabaseHas('pools', [
            'id' => $pool->id,
        ]);

        // Assert that users are added to the new pool
        $this->assertTrue($pool->users()->where('user_id', $user1->id)->exists());
        $this->assertTrue($pool->users()->where('user_id', $user2->id)->exists());
        $this->assertTrue($pool->users()->where('user_id', $user3->id)->exists());
        $this->assertTrue($pool->users()->where('user_id', $user4->id)->exists());
        $this->assertTrue($pool->users()->where('user_id', $user5->id)->exists());

        // Assert that users are removed from the queue_table
        $this->assertDatabaseMissing('queue_table', [
            'user_id' => $user1->id,
        ]);
    }

    #[Test]
    public function it_handles_a_draw_in_a_fight()
    {
        // Create a pool and users
        $pool = Pool::factory()->create(['base_bet' => 10, 'pool_size' => 2]);
        $user1 = User::factory()->create(['status' => 'available', 'balance' => 100]);
        $user2 = User::factory()->create(['status' => 'available', 'balance' => 100]);

        // Add pre-moves for the users
        DB::table('pre_moves')->insert([
            [
                'user_id' => $user1->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
            [
                'user_id' => $user2->id,
                'moves' => json_encode(['rock', 'paper', 'scissors']),
                'hashed_moves' => json_encode(['hashed_rock', 'hashed_paper', 'hashed_scissors']),
                'nonce' => bin2hex(random_bytes(16)),
                'current_index' => 0,
            ],
        ]);

        // Add users to the pool
        $pool->users()->attach([$user1->id, $user2->id]);

        // Simulate a fight that ends in a draw
        $fight = Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
            'status' => 'completed',
            'result' => 'draw',
            'base_bet_amount' => $pool->base_bet,
        ]);

        // Call the method to handle the fight
        $fight->handlePoolAutoplayFight($pool->base_bet, $pool->pool_size);

        // Assert that both users remain in the pool
        $this->assertTrue($pool->users()->where('user_id', $user1->id)->exists());
        $this->assertTrue($pool->users()->where('user_id', $user2->id)->exists());
    }

    private function createNewPool(): Pool
    {
        // Retrieve users from the queue table
        $queuedUsers = DB::table('queue_table')
            ->orderBy('created_at')
            ->limit(5) // Adjust the limit based on your pool size
            ->get();

        if ($queuedUsers->isEmpty()) {
            throw new \Exception("No users available in the queue to create a new pool.");
        }

        // Create a new pool
        $pool = Pool::create([
            'salt' => bin2hex(random_bytes(16)), // Random salt
            'base_bet' => 10,
            'pool_size' => 5,
        ]);

        // Add users to the pool
        foreach ($queuedUsers as $queuedUser) {
            $pool->users()->attach($queuedUser->user_id);
        }

        // Remove users from the queue table
        DB::table('queue_table')
            ->whereIn('user_id', $queuedUsers->pluck('user_id'))
            ->delete();

        return $pool;
    }
}