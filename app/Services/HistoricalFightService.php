<?php
namespace App\Services;

use App\Models\Pool;
use App\Models\Fight;
use App\Models\FHist;
use Illuminate\Support\Facades\Log;

class HistoricalFightService
{
    /**
     * Archive completed fights from a pool by creating historical records.
     */
    public function archivePoolFights($poolId)
    {
        $pool = Pool::findOrFail($poolId);

        $fights = Fight::where('pool_id', $pool->id)
                       ->where('status', 'completed')
                       ->get();

        foreach ($fights as $fight) {
            FHist::create([
                'pool_id'              => $pool->id,
                'user1_id'             => $fight->user1_id,
                'user1_address'        => $fight->user1->wallet_address,
                'user1_balance'        => $fight->user1->balance,
                'user1_battle_balance' => $fight->user1->battle_balance,
                'user1_premove_index'  => 0, // Replace if you have a value
                'user1_move'           => $fight->user1_chosed,
                'user1_gain'           => $fight->user1Gain(),
                'user2_id'             => $fight->user2_id,
                'user2_address'        => $fight->user2->wallet_address,
                'user2_balance'        => $fight->user2->balance,
                'user2_battle_balance' => $fight->user2->battle_balance,
                'user2_premove_index'  => 0, // Replace if you have a value
                'user2_move'           => $fight->user2_chosed,
                'user2_gain'           => $fight->user2Gain(),
            ]);
        }
        return $this->getHistoricalFightData($pool->id);
    }
    public function archiveFight($fightId)
    {
        $fight = Fight::findOrFail($fightId);

        return FHist::create([
            'pool_id'              => $fight->pool_id,
            'user1_id'             => $fight->user1_id,
            'user1_address'        => $fight->user1->wallet_address,
            'user1_balance'        => $fight->user1->balance,
            'user1_battle_balance' => $fight->user1->battle_balance,
            'user1_premove_index'  => 0, // Replace if you have a value
            'user1_move'           => $fight->user1_chosed,
            'user1_gain'           => $fight->user1Gain(),
            'user2_id'             => $fight->user2_id,
            'user2_address'        => $fight->user2->wallet_address,
            'user2_balance'        => $fight->user2->balance,
            'user2_battle_balance' => $fight->user2->battle_balance,
            'user2_premove_index'  => 0, // Replace if you have a value
            'user2_move'           => $fight->user2_chosed,
            'user2_gain'           => $fight->user2Gain(),
        ]);
    }

    /**
     * Return archived fight data in the defined compact format.
     */
    public function getHistoricalFightData($poolId): array
    {
        $historicalFights = FHist::where('pool_id', $poolId)->get();
        $data = [];
        foreach ($historicalFights as $hf) {
            $data[] = [
                $hf->pool_id,
                $hf->user1_address,
                $hf->user1_balance,
                $hf->user1_battle_balance,
                $hf->user1_premove_index,
                $hf->user1_move,
                $hf->user1_gain,
                $hf->user2_address,
                $hf->user2_balance,
                $hf->user2_battle_balance,
                $hf->user2_premove_index,
                $hf->user2_move,
                $hf->user2_gain,
            ];
        }
        return $data;
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
            $data[$poolId][] = [
                'user1_id'             => $hf->user1_id,
                'user1_address'        => $hf->user1_address,
                'user1_balance'        => $hf->user1_balance,
                'user1_battle_balance' => $hf->user1_battle_balance,
                'user1_premove_index'  => $hf->user1_premove_index,
                'user1_move'           => $hf->user1_move,
                'user1_gain'           => $hf->user1_gain,
                'user2_id'             => $hf->user2_id,
                'user2_address'        => $hf->user2_address,
                'user2_balance'        => $hf->user2_balance,
                'user2_battle_balance' => $hf->user2_battle_balance,
                'user2_premove_index'  => $hf->user2_premove_index,
                'user2_move'           => $hf->user2_move,
                'user2_gain'           => $hf->user2_gain,
            ];
        }

        return $data;
    }
}
