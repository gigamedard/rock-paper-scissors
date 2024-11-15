<?php
namespace App\Http\Controllers;

use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AutoMatchController extends Controller
{   
    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10)
    {   
        $depthLimit = config('game_settings.depth_limit');
        $limit = config('game_settings.chunk_size');   // Chunk size
        DB::transaction(function() use ($betAmount, $instanceNumber, $limit) {
            // 1. Retrieve slice_table entry based on current instance number
            $sliceData = DB::table('slice_table')
                           ->where('instance_number', $instanceNumber)
                           ->where('bet_amount', $betAmount)
                           ->where('current_instance', true)
                           ->first();
    
            if (!$sliceData) {
                return; // No active instance found, exit function
            }
    
            // 2. Retrieve last processed user ID and ultimate ID from slice_data
            $lastUserId = $sliceData->last_user_id ?? 0;
            $ultimeId = $sliceData->ultime_user_id ?? 0;

            if($lastUserId == 0)
            {
                $users = User::where('autoplay_active', true)
                ->where('bet_amount', $betAmount)
                ->where('id', '>=', $lastUserId)
                ->where('status', 'available')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();                
            }
            else
            {
                $users = User::where('autoplay_active', true)
                ->where('bet_amount', $betAmount)
                ->where('id', '>', $lastUserId)
                ->where('status', 'available')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();
            }


            // Ensure an even number of users for pairing
            if ($users->count() % 2 !== 0) {
                $excludedUser = $users->pop();
                DB::table('users')->where('id', $excludedUser->id)->update(['locked' => false]);
            }
    
            // 4. Update last_user_id if users are available
            if ($users->isNotEmpty()) {
                $newLastUserId = $users->last()->id;
                DB::table('slice_table')->where('instance_number', $instanceNumber)
                    ->update(['last_user_id' => $newLastUserId]);
            }
    
            // 5. Pair users and create fights
            for ($i = 0; $i < $users->count(); $i += 2) {
                Fight::create([
                    'user1_id' => $users[$i]->id,
                    'user2_id' => $users[$i + 1]->id,
                    'bet_amount' => $betAmount,
                    'status' => 'waiting_for_both'
                ]);
            }
    
            // 6. Increment the depth of the current instance after processing
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


}
