<?php
namespace App\Http\Controllers;

use App\Services\PoolService;
use App\Services\PreMoveService;
use App\Services\HistoricalFightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Helpers\Web3Helper;

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
}
