<?php

namespace Tests\Feature;

use Tests\TestCase;

class GameSettingsTest extends TestCase
{
    /**
     * Test retrieving game settings configuration.
     *
     * @return void
     */
    public function test_game_settings_configuration()
    {
        // Assert that 'bet_amounts' exists and matches the expected array
        $betAmounts = config('game_settings.bet_amounts');

        foreach($betAmounts as $b){
            $this->assertIsInt($b);   
        }
        $this->assertIsArray($betAmounts);
        $this->assertEquals([1, 2, 4, 8, 16], $betAmounts);

        // Assert that 'depth_limit' exists and matches the expected value
        $depthLimit = config('game_settings.depth_limit');
        $this->assertIsInt($depthLimit);
        $this->assertEquals(10, $depthLimit);

        // Assert that 'chunk_size' exists and matches the expected value
        $chunkSize = config('game_settings.chunk_size');
        $this->assertIsInt($chunkSize);
        $this->assertEquals(10, $chunkSize);

        // Assert that 'base_bet' exists and matches the expected value
        $baseBet = config('game_settings.base_bet');
        $this->assertIsInt($baseBet);
        $this->assertEquals(1, $baseBet);
    }
}
