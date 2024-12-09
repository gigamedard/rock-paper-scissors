<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlockchainController extends Controller
{
    public function updateCounter(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'action' => 'required|string',
            'counter' => 'required|numeric',
        ]);

        // Log the action
        Log::info(" log::info Counter Updated via API: Action: {$validated['action']}, Counter: {$validated['counter']}");
        dump(" dump Counter Updated via API: Action: {$validated['action']}, Counter: {$validated['counter']}");
        
        // Update the database
        DB::table('counters')->updateOrInsert(
            ['id' => 1], // Replace '1' with a unique identifier, if needed
            ['value' => $validated['counter']]
        );

        return response()->json(['message' => 'Counter updated successfully'], 200);
    }
}
