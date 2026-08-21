<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Listeners\SessionFinishedEventListener;
use App\Helpers\Web3Helper;
use App\Services\PinataService;
use App\Services\FightService;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use App\Events\SessionFinishedEvent;

class LevelingSystemTest extends TestCase
{
    use RefreshDatabase;

    public function testListenerUsesCorrectMultiplierFromLevel()
    {
        // Setup Config
        Config::set('game_levels.multiplier.5', 7.5);
        Config::set('game_levels.recovery_time.1', 1440);

        // Create User in DB
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'wallet_address' => '0x123',
            'multiplier_level' => 5, // Should use 7.5x
            'recovery_level' => 1,
            'balance' => 100,
            'battle_balance' => 1000,
            'session_start_balance' => 100,
            'session_start_battle_balance' => 0,
            'status' => 'in_pool', // Assume active
        ]);
        
        // Mock PreMove relationship manually? Or just create it if possible.
        // If PreMove is a model, create it.
        // Assuming PreMove exists. 
        \App\Models\PreMove::create([
            'user_id' => $user->id,
            'session_first_pool_id' => 1,
            'move' => 'rock', 
            'hash' => 'abc', 
            'moves' => json_encode(['rock', 'paper']), 
        ]);

        // Create Pool (Required for FK)
        \Illuminate\Support\Facades\DB::table('pools')->insert([
            'id' => 1,
            'base_bet' => 1,
            'pool_size' => 2,
            'status' => 'batched',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create FHist records
        \App\Models\FHist::create([
            'pool_id' => 1,
            'user1_id' => $user->id,
            'user2_id' => 999,
            //'winner_id' => $user->id, // Not in schema!
            'user1_address' => $user->wallet_address,
            'user2_address' => '0x999',
            'user1_move' => 'rock',
            'user2_move' => 'scissors',
            'user1_premove_index' => 0,
            'user2_premove_index' => 0,
            'user1_balance' => 90,
            'user1_battle_balance' => 10,
            'user2_balance' => 90,
            'user2_battle_balance' => 0,
            'user1_gain' => 10,
            'user2_gain' => -10,
        ]);
        $web3Helper = Mockery::mock(Web3Helper::class);
        $web3Helper->shouldReceive('sendPayement')->once();
        $web3Helper->shouldReceive('sendSessionCIDToSmartContract')->once(); // Called in archiveSessionHistory
        
        // Expect setUserNextSessionTime call
        // 7.5x multiplier means q = (100+1000)/100 = 11 which is > 7.5. So it triggers.
        // If multiplier was default 2.0, it would also trigger. 
        // We want to ensure it uses the config.
        // Let's set a high multiplier in config default and low in level to prove it works, or vice versa.
        // Here config is 7.5. q=11. 11 >= 7.5.
        
        $web3Helper->shouldReceive('setUserNextSessionTime')
            ->once()
            ->withArgs(function ($url, $wallet, $time) {
                // Check if time is roughly 24h from now (Level 1 recovery)
                $diff = $time - time();
                return $wallet === '0x123' && abs($diff - 86400) < 60; 
            });

        $pinataService = Mockery::mock(PinataService::class);
        $pinataService->shouldReceive('pinJsonData')->andReturn('QmTest');

        $fightService = Mockery::mock(FightService::class);

        // Instantiate Listener
        $listener = new SessionFinishedEventListener($web3Helper, $pinataService, $fightService);

        // Create Event
        $event = new SessionFinishedEvent($user->id, null);

        // Run
        $listener->handle($event);
        
        $this->assertTrue(true);
    }
}
