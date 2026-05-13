<?php

namespace App\Http\Controllers;

use App\Services\BatchProcessingService;
use App\Services\HistoricalFightService;
use App\Services\PoolService;
use App\Services\PreMoveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PoolAutoMatchController extends Controller
{
    protected $poolService;

    protected $preMoveService;

    protected $historicalFightService;

    protected $batchProcessingService;

    protected $internalPoolService;

    public function __construct(
        PoolService $poolService,
        PreMoveService $preMoveService,
        HistoricalFightService $historicalFightService,
        BatchProcessingService $batchProcessingService,
        \App\Services\InternalPoolService $internalPoolService
    ) {
        $this->poolService = $poolService;
        $this->preMoveService = $preMoveService;
        $this->historicalFightService = $historicalFightService;
        $this->batchProcessingService = $batchProcessingService;
        $this->internalPoolService = $internalPoolService;
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
            'pre_moves' => 'required|array|min:1',
            'user_id' => 'required|integer|exists:users,id',
            'bet_amount' => 'required|numeric|min:0.000001',
            'cid' => 'required|string',
        ]);

        $response = $this->preMoveService->storePreMoves($data);

        // NOTIFICATION: User Joined Pool (or Queue)
        if (isset($response['success']) && $response['success']) {
            \App\Models\GameNotification::create([
                'user_id' => $data['user_id'],
                'type' => 'POOL_JOINED',
                'data' => [
                    'bet_amount' => $data['bet_amount'],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        }

        return response()->json($response);
    }

    public function unregisterFromAutoplay(Request $request): JsonResponse
    {
        $response = $this->preMoveService->unregisterFromAutoplay($request->user());

        return response()->json($response);
    }

    public function getPollingStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'status' => $user->status,
            'autoplay_active' => $user->autoplay_active,
            'session_started' => $user->session_started,
            'balance' => $user->balance,
            'session_start_balance' => $user->session_start_balance,
            'battle_balance' => $user->battle_balance,
            'session_start_battle_balance' => $user->session_start_battle_balance,
            'payout_signature' => $user->payout_signature,
        ]);
    }

    public function poolEmitedRequest(Request $request): JsonResponse
    {
        // Authentication is handled by auth.internal middleware

        $validated = $request->validate([
            'pool_id' => 'required|string',
            'base_bet' => 'required|string',
            'users' => 'required|array',
            'premove_cids' => 'required|array',
            'pool_salt' => 'required|string',
            'balances' => 'required|array',
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

    public function processBatch(Request $request): JsonResponse
    {
        $validated = $request->validate(['base_bet' => 'required|numeric']);
        $result = $this->batchProcessingService->processBatch($validated['base_bet']);

        return response()->json($result, $result['http_code'] ?? 200);
    }

    public function processAllBetTiers(Request $request): JsonResponse
    {
        $result = $this->batchProcessingService->processAllBetTiers();

        return response()->json($result, $result['http_code'] ?? 200);
    }

    public function processInternalPools(Request $request): JsonResponse
    {
        $validated = $request->validate(['base_bet' => 'required|numeric']);

        return response()->json($this->internalPoolService->processInternalPools($validated['base_bet']));
    }

    public function handleStagnantRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pool_id' => 'required|string',
            'refunded_count' => 'required|integer',
            'timestamp' => 'required|integer',
        ]);

        Log::info('Stagnant Pool Refund Processed', $validated);

        return response()->json(['message' => 'Stagnant pool refund logged']);
    }
}
