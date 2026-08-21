<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pool;
use App\Models\Fight;
use App\Models\PreMove;
use App\Events\PoolFinishedEvent;
use App\Events\SessionFinishedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Services\HistoricalFightService;
use App\Services\FightService;

class PoolLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This mock will be used by any service that needs it.
        $historicalFightServiceMock = $this->mock(HistoricalFightService::class, function ($mock) {
            $mock->shouldReceive('archiveFight')->andReturn(new \App\Models\FHist());
        });
        $this->app->instance(HistoricalFightService::class, $historicalFightServiceMock);
    }

    public function test_pool_is_created_with_initial_users()
    {
        $users = User::factory()->count(4)->create();
        $pool = Pool::factory()->create();
        $pool->users()->saveMany($users);

        $this->assertDatabaseHas('pools', ['id' => $pool->id]);
        $this->assertEquals(4, $pool->users->count());
    }

    public function test_duel_removes_loser_and_keeps_winner()
    {
        $user1 = User::factory()->create(['balance' => 100, 'battle_balance' => 10]);
        $user2 = User::factory()->create(['balance' => 100, 'battle_balance' => 10]);
        PreMove::factory()->create(['user_id' => $user1->id, 'moves' => json_encode(['rock'])]);
        PreMove::factory()->create(['user_id' => $user2->id, 'moves' => json_encode(['scissors'])]);
        $pool = Pool::factory()->create(['base_bet' => 10]);
        $pool->users()->saveMany([$user1, $user2]);

        $fight = Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
            'base_bet_amount' => $pool->base_bet,
            'status' => 'waiting_for_result',
        ]);

        app(FightService::class)->handlePoolAutoplayFight($fight, $pool->base_bet, $pool->pool_size);

        $this->assertDatabaseHas('fights', ['id' => $fight->id, 'result' => 'user1_win']);
        $user1->refresh();
        $this->assertEquals($pool->id, $user1->pool_id);
        $this->assertDatabaseMissing('users', ['id' => $user2->id, 'pool_id' => $pool->id]);
    }

    public function test_draw_keeps_both_users_in_pool()
    {
        $user1 = User::factory()->create(['balance' => 100, 'battle_balance' => 10]);
        $user2 = User::factory()->create(['balance' => 100, 'battle_balance' => 10]);
        PreMove::factory()->create(['user_id' => $user1->id, 'moves' => json_encode(['rock'])]);
        PreMove::factory()->create(['user_id' => $user2->id, 'moves' => json_encode(['rock'])]);
        $pool = Pool::factory()->create(['base_bet' => 10]);
        $pool->users()->saveMany([$user1, $user2]);

        $fight = Fight::create([
            'pool_id' => $pool->id,
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
            'base_bet_amount' => $pool->base_bet,
            'status' => 'waiting_for_result',
        ]);

        app(FightService::class)->handlePoolAutoplayFight($fight, $pool->base_bet, $pool->pool_size);

        $this->assertDatabaseHas('fights', ['id' => $fight->id, 'result' => 'draw']);
        $this->assertDatabaseHas('users', ['id' => $user1->id, 'pool_id' => $pool->id]);
        $this->assertDatabaseHas('users', ['id' => $user2->id, 'pool_id' => $pool->id]);
    }

    public function test_pool_population_decreases_after_each_round()
    {
        $users = User::factory()->count(4)->create(['balance' => 100, 'battle_balance' => 10]);
        foreach ($users as $key => $user) {
            PreMove::factory()->create(['user_id' => $user->id, 'moves' => json_encode($key % 2 === 0 ? ['rock'] : ['scissors'])]);
        }
        $pool = Pool::factory()->create(['base_bet' => 10, 'pool_size' => 4]);
        $pool->users()->saveMany($users);

        $this->assertEquals(4, $pool->users->count());

        $pool->match();

        $this->assertEquals(2, $pool->users()->count());
    }

    public function test_pool_stops_when_minimum_size_is_reached()
    {
        Event::fake();

        $users = User::factory()->count(8)->create(['balance' => 100, 'battle_balance' => 10]);
        foreach ($users as $key => $user) {
            $moves = $key < 4 ? ['rock', 'rock', 'rock'] : ['scissors', 'scissors', 'scissors'];
            PreMove::factory()->create(['user_id' => $user->id, 'moves' => json_encode($moves)]);
        }
        $pool = Pool::factory()->create(['base_bet' => 10, 'pool_size' => 8]);
        $pool->users()->saveMany($users);

        config(['pool.percentage_limit_of_pool_size' => 0.125]); // Stop at 1 user.

        app(\App\Services\PoolLifecycleService::class)->processPoolAutoMatch($pool->id);

        $this->assertEquals(1, $pool->users()->count());
        Event::assertDispatched(PoolFinishedEvent::class);
    }

    public function test_winner_receives_correct_winnings()
    {
        Event::fake();

        $user1 = User::factory()->create(['balance' => 100, 'battle_balance' => 10]);
        $user2 = User::factory()->create(['balance' => 100, 'battle_balance' => 10]);
        PreMove::factory()->create(['user_id' => $user1->id, 'moves' => json_encode(['rock'])]);
        PreMove::factory()->create(['user_id' => $user2->id, 'moves' => json_encode(['scissors'])]);

        $pool = Pool::factory()->create(['base_bet' => 10, 'pool_size' => 2]);
        $pool->users()->saveMany([$user1, $user2]);

        config(['pool.percentage_limit_of_pool_size' => 0.5]); // Stop at 1 user.

        $winner = $user1;

        $expectedFinalBalance = 110;

        app(\App\Services\PoolLifecycleService::class)->processPoolAutoMatch($pool->id);

        $winner->refresh();

        $this->assertEquals($expectedFinalBalance, $winner->balance);
        $this->assertEquals(0, $winner->battle_balance);
        Event::assertDispatched(SessionFinishedEvent::class);
    }
}
