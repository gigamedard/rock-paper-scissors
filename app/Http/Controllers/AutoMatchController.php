<?php
namespace App\Http\Controllers;

use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HistoricalDataTrait;


class AutoMatchController extends Controller
{   
    use HistoricalDataTrait;


    public function index()
    {   
        
        if(!Auth::check()) {
            return redirect('/');
        }

        return view('autoplay');
    }

    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10)
    {
        $limit = config('game_settings.chunk_size');
        DB::transaction(function () use ($betAmount, $instanceNumber, $limit) {
            $sliceData = DB::table('slice_table')
                ->where('instance_number', $instanceNumber)
                ->where('bet_amount', $betAmount)
                ->where('current_instance', true)
                ->first();
    
            if (!$sliceData) {
                return;
            }
    
            $lastUserId = $sliceData->last_user_id ?? 0;
    
            $users = User::where('autoplay_active', true)
                ->where('bet_amount', $betAmount)
                ->where('id', '>', $lastUserId)
                ->where('status', 'available')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();
    
            if ($users->count() % 2 !== 0) {
                $users->pop(); // Ensure even count
            }
    
            if ($users->isNotEmpty()) {
                $newLastUserId = $users->last()->id;
                DB::table('slice_table')->where('instance_number', $instanceNumber)
                    ->update(['last_user_id' => $newLastUserId]);
    
                // Generate fights and process them
                for ($i = 0; $i < $users->count(); $i += 2) {
                    $fight = Fight::create([
                        'user1_id' => $users[$i]->id,
                        'user2_id' => $users[$i + 1]->id,
                        'base_bet_amount' => $betAmount,
                        'status' => 'waiting_for_result',
                    ]);
    
                    // Use the built-in method to complete the fight
                    $fight->handleAutoplayFight();
                }
            }
    
            DB::table('slice_table')->where('instance_number', $instanceNumber)
                ->where('bet_amount', $betAmount)
                ->increment('depth');
        });
    }
    

    public function selectSliceInstence($betAmount)
    {   $depthLimit = config('game_settings.depth_limit');
        DB::transaction(function()  use ($betAmount,$depthLimit){
            // 1. Retrieve the current instance
            $currentInstance = DB::table('slice_table')
            ->where('current_instance', true)
            ->where('bet_amount', $betAmount)
            ->first();

            if (!$currentInstance) {
                return; // Exit if no current instance is available
            }

            if ($currentInstance) {
                // 2. Check if current instance has reached depth limit
                if ($currentInstance->depth >= $depthLimit) {
                    // 3. Set the most outdated instance as current
                    $outdatedInstance = DB::table('slice_table')
                        ->where('instance_number', '!=', $currentInstance->instance_number)
                        ->where('bet_amount', $betAmount)
                        ->orderBy('updated_at', 'asc')
                        ->first();

                    if ($outdatedInstance) {
                        // Update instances to reflect the change
                        DB::table('slice_table')->where('id', $currentInstance->id)
                        ->where('bet_amount', $betAmount)
                        ->update(['current_instance' => false]);

                        DB::table('slice_table')->where('id', $outdatedInstance->id)
                        ->where('bet_amount', $betAmount)
                        ->update(['current_instance' => true, 'depth' => 0]); // Reset depth
                    }
                }
            }

            // Proceed with matching for the current instance
            
            $this->processAutoMatch($betAmount,$currentInstance->instance_number);
        });
    }
    public function selectSliceInstenceForAllBetAmount()
    {
        // Iterate through bet amounts in a progressive scale
        $betAmounts = config('game_settings.bet_amounts'); // Bet amounts // Adjust as needed
        foreach ($betAmounts as $betAmount) {
            $this->selectSliceInstence($betAmount);
        }
    }
    
    public function storePreMoves(Request $request)
    {
        $validated = $request->validate([
            'pre_moves' => 'required|array|min:1', // Array of moves
            'user_id' => 'required|integer|exists:users,id', // Valid user ID
            'bet_amount' => 'required|numeric|min:0.0001', // Decimal number with a minimum value
            'cid' => 'required|string' // Validate the CID
        ]);
        


        $cid = $validated['cid']; // Get the CID

        
        

        $nonce = bin2hex(random_bytes(16)); // Generate a unique nonce
        $preMoves = $validated['pre_moves'];
        $bet_amount = $validated['bet_amount'];

        // Hash each move with the nonce
        $hashedMoves = array_map(fn($move) => hash('sha3-256', $move . $nonce), $preMoves);

        // Store in the database
        DB::table('pre_moves')->updateOrInsert(
            ['user_id' => auth()->id()],
            [
                'moves' => json_encode($preMoves),
                'hashed_moves' => json_encode($hashedMoves),
                'nonce' => $nonce,
                'current_index' => 0,
                'cid' => $cid,
            ]
        );

        // Placeholder for storing hashed moves on blockchain
        $this->registerForAutoplay($bet_amount);
        $this->storeOnBlockchain($hashedMoves);

        

        return response()->json([
            'message' => 'Pre-moves stored successfully!',
            'hash' => hash('sha3-256', json_encode($hashedMoves)), // Return a hash of all hashed moves
        ]);
    }

    public function registerForAutoplay($bet_amount)
    {

        $user = auth()->user(); // Get the authenticated user

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Update user's autoplay settings
        $user->update([
            'autoplay_active' => true,
            'bet_amount' => $bet_amount,
            'status' => 'available', // Ensure the user is marked as available
        ]);

        return response()->json(['message' => 'User registered for autoplay successfully!']);
    }

    public function unregisterFromAutoplay()
    {
        $user = auth()->user();
    
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        $user->update([
            'autoplay_active' => false,
            'status' => 'available', // Reset status
        ]);
    
        return response()->json(['message' => 'User unregistered from autoplay successfully!']);
    }
    
    // Placeholder method for blockchain interactions
    private function storeOnBlockchain(array $hashedMoves)
    {
        // Implement blockchain storage logic here
        // Example: Call smart contract to store the hashes
    }
    
    




  
}
