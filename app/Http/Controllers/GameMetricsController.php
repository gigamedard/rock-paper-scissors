<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InternalPoolService;
use App\Services\BatchProcessingService;
use Illuminate\Support\Facades\Log;

class GameMetricsController extends Controller
{
    protected $internalPoolService;
    protected $batchProcessingService;

    public function __construct(
        InternalPoolService $internalPoolService,
        BatchProcessingService $batchProcessingService
    ) {
        $this->internalPoolService = $internalPoolService;
        $this->batchProcessingService = $batchProcessingService;
    }

    public function collect(Request $request)
    {
        try {
            // Retrieve dynamic base bet if needed, using 0.01 for now or fetching from game logic
            // The batch processor orchestrates matching for users that need it
            $this->internalPoolService->processInternalPools('0.01');
            $this->batchProcessingService->processAllBetTiers();
        } catch (\Throwable $e) {
            // Silently log errors, as this is a background tick
            Log::error('Metrics Collection Tick Error: ' . $e->getMessage());
        }

        return response()->json(['status' => 'metrics_collected']);
    }
}
