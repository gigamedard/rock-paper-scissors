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
use Illuminate\Support\Facades\Config;

// Add necessary Use statements
use App\Models\Batch;
use App\Models\Pool;
use Illuminate\Http\JsonResponse; // Use specific JsonResponse for return type hint


use Exception; // Import Exception class

// --- Add New Service Dependencies ---
use App\Services\BatchProcessing\BatchCriteriaService;
use App\Services\BatchProcessing\BatchFinderService;
use App\Services\BatchProcessing\PoolFetcherService;
use App\Services\BatchProcessing\BatchManagerService;
use App\Services\BatchProcessing\PoolProcessorService;
// ---------------------------------

use App\Models\ArrayIndex;


class PoolAutoMatchController extends Controller
{
    protected $poolService;
    protected $preMoveService;
    protected $historicalFightService;
    const POOL_SIZE_INDEX_CACHE_KEY = 'batch_pool_size_index'; // Define cache key

    // Keep existing service injections if needed for other methods
  

    // --- Inject New Services ---
    protected $batchCriteriaService;
    protected $batchFinderService;
    protected $poolFetcherService;
    protected $batchManagerService;
    protected $poolProcessorService;
    // --------------------------

    public function __construct(
        // Keep existing injections
        PoolService $poolService,
        PreMoveService $preMoveService,
        HistoricalFightService $historicalFightService,
        // Add new injections
        BatchCriteriaService $batchCriteriaService,
        BatchFinderService $batchFinderService,
        PoolFetcherService $poolFetcherService,
        BatchManagerService $batchManagerService,
        PoolProcessorService $poolProcessorService
    ) {
        // Assign existing
        $this->poolService = $poolService;
        $this->preMoveService = $preMoveService;
        $this->historicalFightService = $historicalFightService;
        // Assign new
        $this->batchCriteriaService = $batchCriteriaService;
        $this->batchFinderService = $batchFinderService;
        $this->poolFetcherService = $poolFetcherService;
        $this->batchManagerService = $batchManagerService;
        $this->poolProcessorService = $poolProcessorService;
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

    /**
     * i want a function that works this way:
     * 1 the function retrive the curent bach
     * - if they is no bach, it checks if they are at list one pool that need to be auto executed in bach
     *                                              -if they is no pool then return fals
     *                                              -esle :
     *                                                      1 retive $limit pool 
     *                                                      2 create a bach 
     *                                                      3 set bach last pool id to the the id of the last pool on the selection

     * -else check the bach status
     *      -if it is loading :
     *                         1 retrive ($maxsize - numberofpool ) pool that need to executed in bach
     *                         
     *                         2 update number of the pool to the number of pool retrieved + the current value
     *                        
     *                         
     *                         
    *                           8 set status to :
    *                                           -if number of pool >= max size: loaded
    *                                            -else number of pool : loading
    *                            return true
        *      -else if it is loaded or processing :
    *                          1 retrive all pool betwen the first pool id and the last pool id
    *                          2 update the bach status to processing
    *           
    *                          3 update the bach last pool id to the id of the last pool in the selection
    *                          4 process the pools of the selection
    *                          5 increment the bach iteration count
    *                          if the iteration count >= max iteration count:
    *                              1 update the bach status to settled

    *                          
    *                          
     */

    public function _processBatch()
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
                             ->where('status', 'from_server_waitting')
                             ->whereColumn('pool_id', '=', 'id') // Added this line
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
                $batch->update(['status' => 'settled', 'iteration_count'=>0]); //todo when the iteration number reaches the max
                // it is reinitialised to zero.
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

    
    public function processBatch(): JsonResponse
    {
        Log::info('========================= Starting Refactored processBatch =========================');

        // 1. Determine Target Pool Size
        $criteriaResult = $this->batchCriteriaService->getTargetPoolSize();
        if ($criteriaResult['error']) {
            Log::error('Error retrieving target pool size: ' . $criteriaResult['error']);
            return response()->json(['message' => $criteriaResult['error']], 500);
        }
        $targetPoolSize = $criteriaResult['targetPoolSize'];
        Log::info("Target pool size determined: {$targetPoolSize}");

        $poolsToProcess = collect(); // Initialize for potential deferred processing
        $batchForProcessing = null; // Initialize

        try {
            // 2. Transaction 1: Find/Create/Load Batch
            $resultData = DB::transaction(function () use (
                $targetPoolSize,
                &$poolsToProcess, // Pass by reference
                &$batchForProcessing // Pass by reference
            ) {
                Log::info("Starting transaction for target pool size {$targetPoolSize}.");
                
                // Use lockForUpdate within the transaction boundary
                $batch = $this->batchFinderService->findActiveBatchWithLock($targetPoolSize); // Now applies lock
                Log::info("Batch fetched inside transaction: " . ($batch ? $batch->id : 'None'));

                // --- Case: No Active Batch Found ---
                if (!$batch) {
                    Log::info("Entering condition: No active batch found for pool_size {$targetPoolSize}.");
                    if (!$this->poolFetcherService->processablePoolsExist($targetPoolSize)) {
                        Log::info("No processable pools available for pool_size {$targetPoolSize}.");
                        return ['status' => 'no_work', 'message' => "No active batch or available pools for pool_size {$targetPoolSize}."];
                    }

                    $initialLimit = Config::get('pool.batch_initial_limit', 50);
                    $initialPools = $this->poolFetcherService->fetchInitialPools($targetPoolSize, $initialLimit);

                    if ($initialPools->isEmpty()) {
                        Log::info("No pools available to form an initial batch for pool_size {$targetPoolSize}.");
                        return ['status' => 'no_work', 'message' => "No pools available to form an initial batch for pool_size {$targetPoolSize}."];
                    }

                    $createdBatch = $this->batchManagerService->createBatch($targetPoolSize, $initialPools);
                    Log::info("Exiting condition: Created new batch {$createdBatch->id} for pool_size {$targetPoolSize} with status {$createdBatch->status}.");
                    return ['status' => 'created', 'message' => "New batch {$createdBatch->id} for pool_size {$targetPoolSize} created and is {$createdBatch->status}."];
                }

                // --- Case: Batch is Waiting and Needs Loading ---
                if ($batch->status === 'waiting' && $batch->number_of_pools < $batch->max_size) {
                    Log::info("Entering condition: Batch {$batch->id} is waiting and needs loading. (Pools: {$batch->number_of_pools}/{$batch->max_size})");
                    $needed = $batch->max_size - $batch->number_of_pools;
                    $newPools = $this->poolFetcherService->fetchPoolsToLoad($batch, $needed);

                    if ($newPools->isEmpty()) {
                        Log::info("Exiting condition: No new pools found to add to waiting batch {$batch->id}.");
                        $msg = ($batch->number_of_pools > 0)
                               ? "Batch {$batch->id} remains waiting with {$batch->number_of_pools} pools, no new pools found."
                               : "Batch {$batch->id} remains waiting and empty, no new pools found.";
                         return ['status' => 'no_change', 'message' => $msg];
                    }

                    $this->batchManagerService->loadWaitingBatch($batch, $newPools); // Modifies the locked $batch object
                    $poolCount = $newPools->count(); // Use count of pools actually added
                    Log::info("Exiting condition: Batch {$batch->id} updated with {$poolCount} pools. New status: {$batch->status}");
                    return ['status' => 'updated', 'message' => "Batch {$batch->id} (pool_size {$batch->pool_size}) updated with {$poolCount} pools. Status: {$batch->status}"];
                }

                // --- Case: Batch is Ready for Processing ---
                if ($batch->status === 'running' || ($batch->status === 'waiting' && $batch->number_of_pools > 0)) {
                     Log::info("Entering condition: Batch {$batch->id} is ready for processing. Current Status: {$batch->status}. Iteration: {$batch->iteration_count}");
                    if ($batch->status !== 'running') {
                        $batch->status = 'running';
                        $batch->save(); // Mark as running within the transaction
                        Log::info("Batch {$batch->id} status updated to 'running'.");
                    }

                    $retrievedPools = $this->poolFetcherService->fetchPoolsForProcessing($batch);

                    if ($retrievedPools->isEmpty()) {
                         Log::warning("Exiting condition: Batch {$batch->id} is {$batch->status} but no pools found in its range. Reverting to 'waiting'.");
                         $batch->status = 'waiting'; // Revert status
                         $batch->save();
                         return ['status' => 'error', 'message' => "Batch {$batch->id} found no pools in its defined range. Status reverted to waiting."];
                    }

                    // Prepare for deferred processing
                    $poolsToProcess = $retrievedPools; // Assign to outer scope variable
                    $batchForProcessing = $batch; // Assign batch context too
                    Log::info("Exiting condition: Batch {$batch->id} deferred processing setup complete. Pools count: " . $retrievedPools->count());
                    return ['status' => 'processing_deferred']; // Signal to process outside transaction
                }

                // --- Fallback Case ---
                Log::warning("Entering fallback: Batch {$batch->id} (pool_size {$batch->pool_size}) status '{$batch->status}' did not trigger any action.");
                return ['status' => 'no_action', 'message' => "Batch {$batch->id} status '{$batch->status}' did not trigger action."];
            }); // --- End DB::transaction 1 ---

            // 3. Perform Actual Pool Processing (if deferred)
            if (isset($resultData['status']) && $resultData['status'] === 'processing_deferred') {
                Log::info("Entering deferred processing outside transaction.");
                if (!$batchForProcessing || $poolsToProcess->isEmpty()) {
                     Log::error("Deferred processing signaled, but batch context or pool list is missing.", [
                         'batch_exists' => !is_null($batchForProcessing),
                         'pools_empty' => $poolsToProcess->isEmpty(),
                         'batch_id' => $batchForProcessing?->id ?? 'N/A'
                     ]);
                     return response()->json(['message' => 'Internal error during deferred processing setup.'], 500);
                }

                // Call the processor service
                $processingResult = $this->poolProcessorService->processPools($poolsToProcess, $batchForProcessing);
                Log::info("Deferred processing completed. Processed count: " . $processingResult['processedCount']);

                // Call manager service to update status (handles its own transaction)
                $updatedBatch = $this->batchManagerService->updateBatchStatusAfterProcessing(
                    $batchForProcessing->id,
                    !is_null($processingResult['error']), // processingErrorOccurred flag
                    $processingResult['processedCount']
                );
                Log::info("Batch {$updatedBatch->id} status updated after processing. New iteration count: {$updatedBatch->iteration_count}");

                 // Generate final response based on processing outcome
                 $iterationAfterProcessing = $updatedBatch->iteration_count; // Use updated iteration count

                 if ($processingResult['error']) {
                     Log::error("Processing error in batch {$updatedBatch->id}: " . $processingResult['error']->getMessage());
                     return response()->json([
                         'message' => "Batch {$updatedBatch->id} (pool_size {$updatedBatch->pool_size}) processed with errors. Final Status: {$updatedBatch->status}",
                         'processed_count' => $processingResult['processedCount'],
                         'total_in_batch' => $poolsToProcess->count(),
                         'iteration' => $iterationAfterProcessing,
                         'error' => $processingResult['error']->getMessage()
                     ], 207); // Multi-Status
                 } else {
                     Log::info("Batch {$updatedBatch->id} processed successfully with no errors.");
                     return response()->json([
                         'message' => "Batch {$updatedBatch->id} (pool_size {$updatedBatch->pool_size}) processed successfully. Final Status: {$updatedBatch->status}",
                         'processed_count' => $processingResult['processedCount'],
                         'iteration' => $iterationAfterProcessing
                     ], 200); // OK
                 }
            }
            // Handle other results from Transaction 1
            elseif (isset($resultData['status'])) {
                 Log::info("Handling result from transaction with status: {$resultData['status']}");
                 $httpStatusCode = match($resultData['status']) {
                    'no_work', 'no_change' => 200,
                    'created', 'updated' => 201,
                    'error', 'no_action' => 400,
                    default => 200,
                 };
                 Log::info("Exiting processBatch with message: " . $resultData['message']);
                 return response()->json(['message' => $resultData['message'], 'target_pool_size' => $targetPoolSize], $httpStatusCode);
            }
            // Fallback
            else {
                 Log::error("processBatch ended with an unexpected state before processing or known outcome.");
                 return response()->json(['message' => 'An unexpected server error occurred (unknown state).'], 500);
            }

        } catch (Exception $e) {
            Log::error('========================= Error in Refactored processBatch =========================');
            Log::error("Target Pool Size: {$targetPoolSize}. Error: " . $e->getMessage());
            Log::error("Trace: " . $e->getTraceAsString());
            return response()->json(['message' => 'An error occurred during batch processing: ' . $e->getMessage()], 500);
        } finally {
            Log::info('========================= Finished Refactored processBatch =========================');
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
