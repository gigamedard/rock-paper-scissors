<?php
namespace App\Http\Controllers;

use App\Services\PoolService;
use App\Services\PreMoveService;
use App\Services\HistoricalFightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Helpers\Web3Helper;
use Illuminate\Support\Facades\DB;

use App\Models\Batch;
use App\Models\Pool;
use App\Models\ArrayIndex;


class PoolAutoMatchController extends Controller
{
    protected $poolService;
    protected $preMoveService;
    protected $historicalFightService;

    public function __construct(
        PoolService $poolService,
        PreMoveService $preMoveService,
        HistoricalFightService $historicalFightService
    ) {
        $this->poolService = $poolService;
        $this->preMoveService = $preMoveService;
        $this->historicalFightService = $historicalFightService;
    }

    // Auto-match endpoint
    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10)
    {
        $this->poolService->processAutoMatch($betAmount, $instanceNumber, $limit);
        return response()->json(['message' => 'Auto-match processed']);
    }

    // Select a slice instance for a given bet amount
    public function selectSliceInstence($betAmount)
    {
        $this->poolService->selectSliceInstence($betAmount);
        return response()->json(['message' => 'Slice instance selected']);
    }

    // Process all bet amounts
    public function selectSliceInstenceForAllBetAmount()
    {
        $this->poolService->selectSliceInstenceForAllBetAmount();
        return response()->json(['message' => 'All slice instances processed']);
    }

    // Endpoint for storing pre-moves
    public function storePreMoves(Request $request)
    {
        $data = $request->validate([
            'pre_moves'  => 'required|array|min:1',
            'user_id'    => 'required|integer|exists:users,id',
            'bet_amount' => 'required|numeric|min:0.0001',
        ]);

        $response = $this->preMoveService->storePreMoves($data);
        return response()->json($response);
    }

    // Endpoint for unregistering a user from autoplay
    public function unregisterFromAutoplay(Request $request)
    {
        $response = $this->preMoveService->unregisterFromAutoplay($request->user());
        return response()->json($response);
    }

    // Endpoint for processing a pool emitted event
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

        Log::info('PoolAutoMatchController : poolEmitedRequest :  Validated values: ' . json_encode($validated));

        try {
            Log::info('PoolAutoMatchController : poolEmitedRequest :  into try block');
            $result = $this->poolService->handlePoolEmitedEvent($validated);
            Log::info('PoolAutoMatchController : poolEmitedRequest : after handlePoolEmitedEvent');
            return response()->json(['message' => 'Pool emitted Request handled successfully', 'data' => $result], 200);
        } catch (\Exception $e) {
            Log::error('Error in poolEmitedRequest: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Endpoint for archiving pool fights (after pool is complete)
    public function archivePoolFights(Request $request)
    {
        $poolId = $request->input('pool_id');
        $result = $this->historicalFightService->archivePoolFights($poolId);
        return response()->json(['message' => 'Historical fights archived', 'result' => $result]);
    }



    public function processBatch()
    {   
        Log::info('===========================================================================================================================> processBatch()');
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








    public function testhandlePoolEmitedEvent()
    {

        $users = [
            "0xF3A5D3E6A8CFA57Fdb18aAf4aEaf5Dd8A40BF02E",
            "0x1B7d8dF3cF9Ae5A9E8e40f3cB4D3E3eB2aA7e10F",
            "0x9c7A1dBf3F2dE4A7C5b6F1D3A2B9C4E6F8A1D2C3",
            "0x3C9a5aD8E7fA2B4c9e3F8B2D7aF1E6C3b4A5d8f7",
            "0xE6B4d7F3a2C1B9e5F8A4d7C2b3E9F1A5C6D8e7F0"
        ];

        $user1 = User::factory()->create(['wallet_address' => '0xF3A5D3E6A8CFA57Fdb18aAf4aEaf5Dd8A40BF02E']);
        $user2 = User::factory()->create(['wallet_address' => '0x1B7d8dF3cF9Ae5A9E8e40f3cB4D3E3eB2aA7e10F']);
        $user3 = User::factory()->create(['wallet_address' => '0x9c7A1dBf3F2dE4A7C5b6F1D3A2B9C4E6F8A1D2C3']);
        $user4 = User::factory()->create(['wallet_address' => '0x3C9a5aD8E7fA2B4c9e3F8B2D7aF1E6C3b4A5d8f7']);
        $user5 = User::factory()->create(['wallet_address' => '0xE6B4d7F3a2C1B9e5F8A4d7C2b3E9F1A5C6D8e7F0']);

        Log::info($user1->status);
        Log::info($user2->status);
        Log::info($user3->status);
        Log::info($user4->status);
        Log::info($user5->status);


        // Simulate a CID mismatch by setting a different CID in the user's pre-move
        $user1->preMove()->create([
            'moves' => json_encode(['rock', 'paper', 'scissors']),
            'cid' => 'QmTestCID1', // Simulate CID mismatch
        ]);

        $user2->preMove()->create([
            'moves' => json_encode(['paper', 'paper', 'scissors']),
            'cid' => 'QmTestCID2', // Simulate CID mismatch
        ]);

        $user3->preMove()->create([
            'moves' => json_encode(['rock', 'rock', 'scissors']),
            'cid' => 'QmTestCID3', // Simulate CID mismatch
        ]);

        $user4->preMove()->create([
            'moves' => json_encode(['rock', 'paper', 'rock']),
            'cid' => 'QmTestCID4', // Simulate CID mismatch
        ]);

        $user5->preMove()->create([
            'moves' => json_encode(['rock', 'rock', 'rock']),
            'cid' => 'QmTestCID5', // Simulate CID mismatch
        ]);




        // Mock data for testing
        $poolId = 1;
        $baseBet = 500000000000000000;

        $premoveCIDs = [
            'QmTestCID1',
            'QmTestCID2',
            'QmTestCID3',
            'QmTestCID4',
            'QmTestCID5',
        ];

        $poolSalt = Web3Helper::generateHash($users);

        $data = [
            'pool_id' => $poolId,
            'base_bet' => $baseBet,
            'users' => json_encode($users),
            'premove_cids' => json_encode($premoveCIDs),
            'pool_salt' => $poolSalt,
        ];
    
        try {
            // Call the actual method with test data
            Log::info('$pooId===testhandlePoolEmitedEvent:'. $poolId);
            $this->poolService->handlePoolEmitedEvent($data);
    
            return response()->json(['message' => 'handlePoolEmitedEvent executed successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    //simulate a user registering for auto play
    public function simulateUser(Request $request)
    {
        try {

    
            // Retrieve the GET parameters from the query string
            $preMoves = $request->query('pre_moves');
            $betAmount = $request->query('bet_amount');
            $cid = $request->query('cid');

    
            // Check if required parameters are provided
            if (empty($preMoves) || empty($betAmount) || empty($cid)) {
                return response()->json([
                    'message' => 'Missing required parameters',
                ], 400);
            }
    
            // Decode the pre_moves array (since it's sent as a string)
            $preMoves = json_decode($preMoves, true);
    
            if (!is_array($preMoves) || count($preMoves) < 1) {
                return response()->json([
                    'message' => 'Invalid pre-moves format',
                ], 400);
            }
    
            // Generate a nonce and hash moves
            $nonce = bin2hex(random_bytes(16)); // Generate a unique nonce
            $hashedMoves = array_map(fn($move) => hash('sha3-256', $move . $nonce), $preMoves);
    
            // Log the data
            Log::info('Pre-moves data processed successfully', [
                'pre_moves' => $preMoves,
                'hashed_moves' => $hashedMoves,
                'nonce' => $nonce,
            ]);
    
            // Store in the database
            // Check if the user already exists by wallet_address
            $user = User::where('wallet_address', $request->query('wallet_address'))->first();

            // If user doesn't exist, create one
            if (!$user) {
                $user = User::factory()->create([
                    'wallet_address' => $request->query('wallet_address')
                ]);
            }

            // Use the valid user ID
            $userId = $user->id;

            // Insert or update pre_moves using the found or newly created user ID
            DB::table('pre_moves')->updateOrInsert(
                ['cid' => $cid], 
                [
                    'user_id' => $userId, // Use the correct user ID
                    'moves' => json_encode($preMoves),
                    'hashed_moves' => json_encode($hashedMoves),
                    'nonce' => $nonce,
                    'current_index' => 0,
                    'cid' => $cid,
                    'updated_at' => now(),
                ]
            );
    
            Log::info('Pre-moves stored in database successfully');

    
            return response()->json([
                'message' => 'Pre-moves stored successfully!',
                'hash' => hash('sha3-256', json_encode($hashedMoves)), // Return a hash of all hashed moves
            ]);
        } catch (\Throwable $th) {
            Log::error('Error in storing pre-moves', ['error' => $th->getMessage()]);
    
            return response()->json([
                'message' => 'Error in storing pre-moves!',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    
    
}
