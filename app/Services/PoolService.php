<?php

namespace App\Services;

class PoolService
{
    protected $autoMatchService;
    protected $poolLifecycleService;

    public function __construct(
        AutoMatchService $autoMatchService,
        PoolLifecycleService $poolLifecycleService
    ) {
        $this->autoMatchService = $autoMatchService;
        $this->poolLifecycleService = $poolLifecycleService;
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
        return $this->poolLifecycleService->handlePoolEmitedEvent($data);
    }

    public function processPoolAutoMatch(int $poolId)
    {
        return $this->poolLifecycleService->processPoolAutoMatch($poolId);
    }
}
