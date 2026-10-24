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
            // Base bet aligné sur le serveur (run_batch_processor.js BASE_BET) :
            // 0.0004 (palier 1). L'ancien 0.01 excluait tous les paliers 0.0004–0.0064.
            $baseBet = config('game_settings.base_bet', 0.0004);
            $this->internalPoolService->processInternalPools($baseBet);
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
