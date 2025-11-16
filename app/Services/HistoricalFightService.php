<?php

namespace App\Services;

use App\Models\Fight;
use App\Models\FHist;
use App\Helpers\Web3Helper;
use App\Services\PinataService;
use App\Transformers\HistoricalDataTransformer;
use Illuminate\Support\Facades\Log;

class HistoricalFightService
{
    protected $pinataService;
    protected $web3Helper;
    protected $transformer;

    public function __construct(
        PinataService $pinataService,
        Web3Helper $web3Helper,
        HistoricalDataTransformer $transformer
    ) {
        $this->pinataService = $pinataService;
        $this->web3Helper = $web3Helper;
        $this->transformer = $transformer;
    }

    /**
     * Archive completed fights from a pool by creating historical records.
     */
    public function archivePoolFights($poolId)
    {
        $historicalFights = FHist::where('pool_id', $poolId)->get();
        $data = $this->transformer->transformCollection($historicalFights);
        $cid = $this->pinataService->pinJsonData(json_encode($data));
        $nodeUrl = env('NODE_URL');
        return $this->web3Helper->sendPoolCIDToSmartContract($nodeUrl, $cid, $poolId);
    }

    public function archiveFight($fightId, FHist $fHist = null)
    {
        $fight = Fight::findOrFail($fightId);

        if ($fHist) {
            return $this->updateExistingFHist($fight, $fHist);
        } else {
            return $this->createNewFHist($fight);
        }
    }

    /**
     * Return archived fight data in the defined compact format.
     */
    public function getHistoricalFightData($poolId): array
    {
        $historicalFights = FHist::where('pool_id', $poolId)->get();
        return $this->transformer->transformCollection($historicalFights);
    }

    public function getUserHistoricalFights($userId): array
    {
        $historicalFights = FHist::where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
            ->orderBy('pool_id', 'asc')
            ->get();

        $data = [];
        foreach ($historicalFights as $hf) {
            $poolId = $hf->pool_id;
            if (!isset($data[$poolId])) {
                $data[$poolId] = [];
            }
            $data[$poolId][] = $this->transformer->transform($hf);
        }

        return $data;
    }

    private function updateExistingFHist(Fight $fight, FHist $fHist): FHist
    {
        $ndata = [
            'user1_move' => $fight->user1_chosed,
            'user2_move' => $fight->user2_chosed,
            'user1_balance' => $fight->user1->balance,
            'user1_battle_balance' => $fight->user1->battle_balance,
            'user2_balance' => $fight->user2->balance,
            'user2_battle_balance' => $fight->user2->battle_balance,
            'fight_id' => $fight->id,
        ];

        try {
            $fHist->update($ndata);
        } catch (\Exception $e) {
            Log::error('Failed to update FHist record: ' . $e->getMessage());
        }

        return $fHist;
    }

    private function createNewFHist(Fight $fight): FHist
    {
        $old1 = $this->getOldBalance($fight->user1);
        $old2 = $this->getOldBalance($fight->user2);

        $data = [
            'pool_id' => $fight->pool_id,
            'user1_id' => $fight->user1_id,
            'user1_address' => $fight->user1->wallet_address,
            'old_user1_balance' => $old1,
            'user1_balance' => $fight->user1->balance,
            'user1_battle_balance' => $fight->user1->battle_balance,
            'user1_premove_index' => $fight->user1->preMove->current_index,
            'user1_move' => $fight->user1_chosed,
            'user1_gain' => $fight->user1Gain(),
            'user2_id' => $fight->user2_id,
            'user2_address' => $fight->user2->wallet_address,
            'old_user2_balance' => $old2,
            'user2_balance' => $fight->user2->balance,
            'user2_battle_balance' => $fight->user2->battle_balance,
            'user2_premove_index' => $fight->user2->preMove->current_index,
            'user2_move' => $fight->user2_chosed,
            'user2_gain' => $fight->user2Gain(),
        ];

        return FHist::create($data);
    }

    private function getOldBalance($user)
    {
        $fHist = FHist::where('user1_id', $user->id)
            ->orWhere('user2_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($fHist) {
            return ($fHist->user1_id == $user->id) ? $fHist->old_user1_balance : $fHist->old_user2_balance;
        }

        return $user->balance;
    }
}
