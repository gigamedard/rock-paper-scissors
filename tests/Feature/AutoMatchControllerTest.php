<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Fight;
use Illuminate\Support\Facades\DB;

class AutoMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testProcessAutoMatch()
    {
        // Seed the database with test data
        $betAmount = 100;
        $instanceNumber = 1;

        // Create slice table entry
        DB::table('slice_table')->insert([
            'instance_number' => $instanceNumber,
            'bet_amount' => $betAmount,
            'current_instance' => true,
            'last_user_id' => 0,
            'depth' => 0,
        ]);

        // Create test users
        User::factory()->count(4)->create([
            'autoplay_active' => true,
            'bet_amount' => $betAmount,
            'status' => 'available',
        ]);

        // Call the matching process
        $response = $this->get("/test-matching/$betAmount/$instanceNumber");

        // Assert the response
        $response->assertStatus(200);
        $response->assertSee("Matching completed for bet amount: $betAmount, instance number: $instanceNumber");

        // Assert that fights are created
        $this->assertCount(2, Fight::all());

        // Assert that slice table is updated
        $sliceData = DB::table('slice_table')->where('instance_number', $instanceNumber)->first();
        $this->assertNotNull($sliceData->last_user_id);
    }
}

