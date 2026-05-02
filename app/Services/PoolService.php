<?php

namespace App\Services;

class PoolService
{
    protected $autoMatchService;
    protected $poolReconstructionService;
    protected $matchmakingEngine;

    public function __construct(
        AutoMatchService $autoMatchService,
        PoolReconstructionService $poolReconstructionService,
        MatchmakingEngine $matchmakingEngine
    ) {
        $this->autoMatchService = $autoMatchService;
        $this->poolReconstructionService = $poolReconstructionService;
        $this->matchmakingEngine = $matchmakingEngine;
    }

    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10)
    {
        return $this->autoMatchService->processAutoMatch($betAmount, $instanceNumber, $limit);
    }

    public function selectSliceInstence($betAmount)
    {
        return $this->autoMatchService->selectSliceInstence($betAmount);
    }

    public function selectSliceInstenceForAllBetAmount()
    {
        return $this->autoMatchService->selectSliceInstenceForAllBetAmount();
    }

    public function handlePoolEmitedEvent(array $data)
    {
        $result = $this->poolReconstructionService->reconstruct($data);

        if ($result['status'] === 'processed' && isset($result['pool_id'])) {
            $this->matchmakingEngine->process($result['pool_id']);
        }

        return $result;
    }

    public function processPoolAutoMatch(int $poolId)
    {
        return $this->matchmakingEngine->process($poolId);
    }
}
