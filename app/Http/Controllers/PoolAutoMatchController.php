<?php
namespace App\Http\Controllers;

use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HistoricalDataTrait;
use Guzzlehttp\Client;
use GuzzleHttp\Promise\Utils;
use App\Models\Pool;
use App\Models\PreMove;
use App\Models\ArrayIndex;
use Illuminate\Support\Facades\Log;
use App\Models\Batch;

class PoolAutoMatchController extends Controller
{   
    use HistoricalDataTrait;


   /* public function index()
    {   
        
        if(!Auth::check()) {
            return redirect('/');
        }

        return view('autoplay');
    }*/

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
        ]);
        
        
        

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
    



/**
 * Upload pre-move data to Pinata and return the CID.
 *
 * @param string $preMoveDataJson JSON string of pre-move data
 * @return string|null
 */
public function uploadPreMoveToPinata(string $preMoveDataJson): ?string
{
    // Decode the JSON string into an array
    $preMoveData = json_decode($preMoveDataJson, true);

    // Validate that the JSON was decoded successfully
    if (json_last_error() !== JSON_ERROR_NONE) {
        Log::error('Invalid JSON provided for pre-move data.');
        return null;
    }

    $client = new Client([
        'base_uri' => 'https://api.pinata.cloud/',
        'headers' => [
            'pinata_api_key' => env('PINATA_API_KEY'),
            'pinata_secret_api_key' => env('PINATA_SECRET_KEY'),
        ],
    ]);

    try {
        $response = $client->post('pinning/pinJSONToIPFS', [
            'json' => [
                'pinataContent' => $preMoveData,
            ],
        ]);

        $responseData = json_decode($response->getBody()->getContents(), true);
        return $responseData['IpfsHash'] ?? null; // Return the CID
    } catch (\Exception $e) {
        Log::error('Failed to upload pre-move data to Pinata: ' . $e->getMessage());
        return null;
    }
}







    //todo take bet_amount in paraameter, updatedate migration to add bet_amount, update,pool model too for masse assigment
    public function _handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt)
    {
        // Record the pool data in the database
        //todo do: implement the migration for the pools table
        Pool::create([
            'pool_id' => $poolId,
            'base_bet' => $baseBet,
            'users' => json_encode($users),
            'premove_cids' => json_encode($premoveCIDs),
            'pool_salt' => $poolSalt,
            'pool_size' => count($users)
        ]);
        // fetch user premove data from pinata based on the CID
        // Fetch user premove data from Pinata based on the CID
        $client = new \GuzzleHttp\Client();
        $premoveData = [];

        foreach ($premoveCIDs as $cid) {
            $response = $client->get("https://gateway.pinata.cloud/ipfs/{$cid}");
            if ($response->getStatusCode() === 200) {
            $premoveData[] = json_decode($response->getBody()->getContents(), true);
            }
        }

        // Store the fetched premove data in the database
        foreach ($users as $index => $user) {
            DB::table('pre_moves')->updateOrInsert(
            ['user_id' => User::where('wallet_address', $user)->first()->id],
            ['moves' => json_encode($premoveData[$index])],
            ['current_index' => 0]
            );
            
        }
        // Mark users as in_pool (not available for other pools)
        User::whereIn('wallet_address', $users)->update(['status' => 'in_pool']);

        // Proceed to battle
        //$this->processPoolAutoMatch($poolId);
        $this->processBatch();
  
    }

    public function poolEmitedRequest(Request $request)
    {   
        $token = $request->query('token');

        // Validate the token
        if ($token !== env('INNER_SCRIPT_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('===================================>befor validation ');
    
        // Validate all fields as strings
        $validated = $request->validate([
            'pool_id' => 'required|string',
            'base_bet' => 'required|string', // Validate as string first
            'users' => 'required|string',    // Validate as string first
            'premove_cids' => 'required|string', // Validate as string first
            'pool_salt' => 'required|string',
        ]);
    
        Log::info('===================================>after validation ');
    
        // Manually cast values to their expected types
        $poolId = $validated['pool_id']; // Already a string
        $baseBetStr = $validated['base_bet']; // Cast to float/numeric

        //$baseBetInWei = bcmul($baseBetStr, '1000000000000000000', 0); // Convert to Wei

        $baseBet = $this->weiToEther($baseBetStr); // Convert to Ether
    
        // Convert the users string into an array
        $users = explode(',', $validated['users']); // Split the string by commas
    
        // Convert the premove_cids string into an array
        $premoveCIDs = explode(',', $validated['premove_cids']); // Split the string by commas
    
        $poolSalt = $validated['pool_salt']; // Already a string
    
        // Ensure the arrays have at least 2 elements
        if (count($users) < 2 || count($premoveCIDs) < 2) {
            return response()->json(['error' => 'Arrays must have at least 2 elements'], 422);
        }
    
        try {
            $this->handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt);
            Log::info('===================================>$this->handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt);');
            return response()->json(['message' => 'Pool emitted Request handled successfully'], 200);
        } catch (\Exception $e) {
            Log::error('error in poolEmitedRequest: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
        
    }
    private function weiToEther($wei)
    {
        return bcdiv($wei, '1000000000000000000', 18); // 1 Ether = 10^18 Wei
    }
    public function handlePoolEmittedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt)
    {   

        // Decode JSON strings if necessary
        if (is_string($users)) {
            $users = json_decode($users, true); // Convert JSON string to PHP array
            if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format for users.');
            }
        }

        if (is_string($premoveCIDs)) {
            $premoveCIDs = json_decode($premoveCIDs, true); // Convert JSON string to PHP array
            if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format for premoveCIDs.');
            }
        }

    // Log decoded values for debugging
    Log::info('Decoded users: ' . json_encode($users));
    Log::info('Decoded pre-move CIDs: ' . json_encode($premoveCIDs));

        // Validate input data
        if (empty($poolId) || empty($baseBet) || empty($users) || empty($premoveCIDs) || empty($poolSalt)) {
            throw new \InvalidArgumentException('All parameters must be non-empty.');
        }
    
        // Log the number of users and pre-move CIDs
        Log::info('Number of users: ' . count($users));
        Log::info('Number of pre-move CIDs: ' . count($premoveCIDs));

        // Start database transaction
        DB::transaction(function () use ($poolId, $baseBet, $users, $poolSalt, $premoveCIDs) {
            Log::info(' Start database transaction with $user : ' . json_encode($users));
            try {
                // Create the pool
                $pool = Pool::create([
                    'pool_id' => $poolId,
                    'base_bet' => $baseBet,
                    'salt' => $poolSalt,
                    'pool_size' => count($users),
                ]);
    
                // Fetch all users in one query
                $usersCollection = User::whereIn('wallet_address', $users)->get();
    
                // Store the fetched premove data in the database and associate with users
                
                foreach ($usersCollection as $index => $user) {
                    // Ensure the CID matches the stored CID for the current user
                    if ($user->preMove->cid !== $premoveCIDs[$index]) {
                        Log::error("CID mismatch for user: {$user->wallet_address}");
                        continue;
                    }

                }
                
                // Mark users as in_pool (not available for other pools)
                $usersCollection->each(function ($user) {
                    $user->update(['status' => 'in_pool']);
                });
    
                // Attach users to the pool
                $pool->users()->attach($usersCollection->pluck('id')->toArray());
    
                // Proceed to batch processing
                $this->processBatch();
                return response()->json(['message' => 'Pool emitted event handled successfully'], 200);
            } catch (\Exception $e) {
                // Log the error and re-throw the exception
                Log::error('Error in handlePoolEmitedEvent: ' . $e->getMessage());
                throw $e;
            }
        });
    }
    public function testHandlePoolEmittedEvent()
    {


        $user1 = User::factory()->create(['wallet_address' => '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266']);
        $user2 = User::factory()->create(['wallet_address' => '0x70997970C51812dc3A010C7d01b50e0d17dc79C8']);

        // Simulate a CID mismatch by setting a different CID in the user's pre-move
        $user1->preMove()->create([
            'moves' => json_encode(['rock', 'paper', 'scissors']),
            'cid' => 'QmTestCID1', // Simulate CID mismatch
        ]);



        $user2->preMove()->create([
            'moves' => json_encode(['paper', 'paper', 'scissors']),
            'cid' => 'QmTestCID2', // Simulate CID mismatch
        ]);
        // Mock data for testing
        $poolId = 'test-pool-id-123';
        $baseBet = 0.1;
        $users = [
            '0xUserWalletAddress1',
            '0xUserWalletAddress2',
        ];
        $premoveCIDs = [
            'QmTestCID1',
            'QmTestCID2',
        ];
        $poolSalt = 'random_salt_string';
    
        try {
            // Call the actual method with test data
            $this->handlePoolEmitedEvent($poolId, $baseBet, $users, $premoveCIDs, $poolSalt);
    
            return response()->json(['message' => 'handlePoolEmittedEvent executed successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function processPoolAutoMatch(int $poolId): void
    {
       
            // Retrieve the pool with its available users
            $pool = Pool::with(['users' => function ($query) {
                $query->where('status', 'available')->orderBy('id');
            }])->where('pool_id', $poolId)->firstOrFail();

            // Get available users from the pool
            $availableUsers = $pool->users;

            // Verify balance and update for each user
            foreach ($availableUsers as $user) {
                if ($user->balance >= $pool->base_bet) {
                    $user->balance -= $pool->base_bet;
                    $user->battle_balance += $pool->base_bet;
                    $user->save();
                } else {
                    // Remove user from the collection if balance is insufficient
                    $availableUsers = $availableUsers->reject(function ($u) use ($user) {
                        return $u->id === $user->id;
                    });
                }
            }

            // Ensure even count of users
            if ($availableUsers->count() % 2 !== 0) {
                $availableUsers->pop();
            }

            // Generate fights and process them
            if ($availableUsers->isNotEmpty()) {
                for ($i = 0; $i < $availableUsers->count(); $i += 2) {
                    $fight = Fight::create([
                        'user1_id' => $availableUsers[$i]->id,
                        'user2_id' => $availableUsers[$i + 1]->id,
                        'base_bet_amount' => $pool->base_bet,
                        'status' => 'waiting_for_result',
                    ]);

                    // Use a different method to complete the pool fight
                    $fight->handlePoolAutoplayFight(pool->baseBet, $pool->poolSize);
                }
            }
        
    }
    private function updateOrCreatePreMove(int $userId, array $moves, string $nonce = null): void
    {
        // Update or create pre-moves for the user
        PreMove::updateOrCreate(
            ['user_id' => $userId], // Search condition
            [
                'moves' => json_encode($moves),
                'hashed_moves' => $this->hashMoves($moves, $nonce),
                'nonce' => $nonce,
                'current_index' => 0, // Reset the move index
            ]
        );
    }
    
    private function hashMoves(array $moves, string $nonce = null): array
    {
        // Hash each move with the nonce (if provided)
        return array_map(fn($move) => hash('sha3-256', $move . $nonce), $moves);
    }

    public function processBatch()
    {   

        // Get the current index for `base_bet` and `size`
        $baseBetArray = config('pool.base_bet');
        $sizeArray = config('pool.size');
        $numberOfBaseBets = config('pool.number_of_base_bet');
        Log::info('Number of base bets: ' . $numberOfBaseBets);
        $numberOfPoolSizes = config('pool.number_of_pool_size');
        
        // Log all the selected variables
        Log::info('Base bet array: ' . json_encode($baseBetArray));
        Log::info('Size array: ' . json_encode($sizeArray));
        Log::info('Number of pool sizes: ' . $numberOfPoolSizes);

        $baseBetIndex = $this->getAndUpdateIndex('base_bet', $numberOfBaseBets);
        $sizeIndex = $this->getAndUpdateIndex('size', $numberOfPoolSizes);

        // Pick the `base_bet` and `size` values
        $desiredBaseBet = $baseBetArray[$baseBetIndex];
        $desiredSize = $sizeArray[$sizeIndex];

        // Fetch the next `n` pools starting from `first_pool_id`, filtered by `size` and `base_bet`
        $batchSize = config('pool.bach_size');; // Define your batch size (e.g., 10 pools per batch)
        // Start a database transaction

        DB::transaction(function () use ($desiredSize,$desiredBaseBet,$batchSize) {
            try {
                // Fetch the current batch and lock it for update
                $batch = Batch::where('status', 'waiting')
                              ->orWhere('status', 'running')
                              ->orderBy('id')
                              ->lockForUpdate() // Lock the batch row
                              ->first();
        
                if (!$batch) {
                    DB::rollBack(); // Roll back the transaction
                    return response()->json(['message' => 'No batches available'], 404);
                }
        
    
                $pools = Pool::where('pool_id', '>=', $batch->first_pool_id)
                             ->where('pool_size', $desiredSize)
                             ->where('base_bet', $desiredBaseBet)
                             ->orderBy('pool_id')
                             ->take($batchSize)
                             ->get();
        
                // Handle case where no pools match the criteria
                if ($pools->isEmpty()) {
                    $batch->update(['status' => 'settled']);
                    return response()->json(['message' => 'No pools match the criteria'], 404);
                }
        
                // Determine the first and last pool IDs for the batch
                $firstPoolId = $pools->first()->id;
                $lastPoolId = $pools->last()->id;
        
                // Update the batch with the new `first_pool_id` for the next batch
                $nextPool = Pool::where('pool_id', '>', $lastPoolId)
                                ->where('pool_size', $desiredSize)
                                ->where('base_bet', $desiredBaseBet)
                                ->orderBy('pool_id')
                                ->first();
        
                if ($nextPool) {
                    $batch->update(['first_pool_id' => $nextPool->id]);
                } else {
                    // No more pools to process, mark the batch as settled
                    $batch->update(['status' => 'settled']);
                }
        
            
            }
            catch(\Exception $e){
                DB::rollBack(); // Roll back the transaction
                return response()->json(['message' => 'An error occurred'], 500);
            }    // Release the lock and commit the transaction
        });
    
        try {
    
            // Now process the pools without holding the lock
            foreach ($pools as $pool) {
                $pool->match(); // Call the match method
            }
    
            // Increment iteration count
            $batch->increment('iteration_count');
    
            // Update batch status based on conditions
            if ($batch->iteration_count >= $maxIterations) { // Define $maxIterations as needed
                $batch->update(['status' => 'settled']);
            } else {
                $batch->update(['status' => 'waiting']);
            }
    
            return response()->json([
                'message' => 'Batch processed successfully',
                'base_bet' => $desiredBaseBet,
                'size' => $desiredSize
            ]);
        } catch (\Exception $e) {
            // Roll back the transaction in case of an error
            DB::rollBack();
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    
    private function getAndUpdateIndex($arrayName, $arrayLength)
    {
        // Fetch or create the index record
        $indexRecord = ArrayIndex::firstOrCreate(
            ['array_name' => $arrayName],
            ['current_index' => 0]
        );

        // Get the current index
        $currentIndex = $indexRecord->current_index;

        // Increment the index and reset to 0 if it exceeds the array length
        $newIndex = ($currentIndex + 1) % $arrayLength;

        // Update the index in the database
        $indexRecord->update(['current_index' => $newIndex]);

        return $currentIndex; // Return the current index (before incrementing)
    }
}
