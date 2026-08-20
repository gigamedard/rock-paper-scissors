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
        // SECURITY/DoS fix: serialize concurrent ticks with a distributed lock.
        // Multiple connected clients each fire /metrics/collect every 5s; without
        // a lock, processInternalPools (locks `users`) and processAllBetTiers
        // (locks `pools`) run concurrently and deadlock on MySQL FOR UPDATE.
        $lock = \Illuminate\Support\Facades\Cache::lock('metrics_collect_tick', 10);

        if (!$lock->get()) {
            // Another tick is already running — skip this one (fire-and-forget).
            return response()->json(['status' => 'skipped']);
        }

        try {
            // Retrieve dynamic base bet if needed, using 0.01 for now or fetching from game logic
            // The batch processor orchestrates matching for users that need it
            $this->internalPoolService->processInternalPools('0.01');
            $this->batchProcessingService->processAllBetTiers();
        } catch (\Throwable $e) {
            // Silently log errors, as this is a background tick
            Log::error('Metrics Collection Tick Error: ' . $e->getMessage());
        } finally {
            $lock->release();
        }

        return response()->json(['status' => 'metrics_collected']);
    }
}
