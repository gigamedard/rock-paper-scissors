<?php
namespace App\Http\Controllers;

use App\Services\PoolService;
use App\Services\PreMoveService;
use App\Services\HistoricalFightService;
use App\Services\BatchProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PoolAutoMatchController extends Controller
{
    protected $poolService;
    protected $preMoveService;
    protected $historicalFightService;
    protected $batchProcessingService;

    public function __construct(
        PoolService $poolService,
        PreMoveService $preMoveService,
        HistoricalFightService $historicalFightService,
        BatchProcessingService $batchProcessingService
    ) {
        $this->poolService = $poolService;
        $this->preMoveService = $preMoveService;
        $this->historicalFightService = $historicalFightService;
        $this->batchProcessingService = $batchProcessingService;
    }

    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10): JsonResponse
    {
        $this->poolService->processAutoMatch($betAmount, $instanceNumber, $limit);
        return response()->json(['message' => 'Auto-match processed']);
    }

    public function selectSliceInstence($betAmount): JsonResponse
    {
        $this->poolService->selectSliceInstence($betAmount);
        return response()->json(['message' => 'Slice instance selected']);
    }

    public function selectSliceInstenceForAllBetAmount(): JsonResponse
    {
        $this->poolService->selectSliceInstenceForAllBetAmount();
        return response()->json(['message' => 'All slice instances processed']);
    }

    public function storePreMoves(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pre_moves'  => 'required|array|min:1',
            'user_id'    => 'required|integer|exists:users,id',
            'bet_amount' => 'required|numeric|min:0.000001',
            'cid'        => 'required|string',
        ]);

        $response = $this->preMoveService->storePreMoves($data);
        return response()->json($response);
    }

    public function unregisterFromAutoplay(Request $request): JsonResponse
    {
        $response = $this->preMoveService->unregisterFromAutoplay($request->user());
        return response()->json($response);
    }

    public function poolEmitedRequest(Request $request): JsonResponse
    {
        // Authentication is handled by auth.internal middleware
        
        $validated = $request->validate([
            'pool_id'      => 'required|string',
            'base_bet'     => 'required|string',
            'users'        => 'required|array',
            'premove_cids' => 'required|array',
            'pool_salt'    => 'required|string',
        ]);

        try {
            $serviceData = $validated;
            $serviceData['users'] = implode(',', $validated['users']);
            $serviceData['premove_cids'] = implode(',', $validated['premove_cids']);

            $result = $this->poolService->handlePoolEmitedEvent($serviceData);

            return response()->json(['message' => 'Pool emitted Request handled successfully', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function archivePoolFights(Request $request): JsonResponse
    {
        $poolId = $request->input('pool_id');
        $result = $this->historicalFightService->archivePoolFights($poolId);
        return response()->json(['message' => 'Historical fights archived', 'result' => $result]);
    }

    public function processBatch(): JsonResponse
    {
        return $this->batchProcessingService->processBatch();
    }

    public function handleStagnantRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pool_id' => 'required|string',
            'refunded_count' => 'required|integer',
            'timestamp' => 'required|integer',
        ]);

        Log::info("Stagnant Pool Refund Processed", $validated);
        
        return response()->json(['message' => 'Stagnant pool refund logged']);
    }
}
