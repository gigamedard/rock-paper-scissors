<?php

namespace App\Transformers;

use App\Models\FHist;

class HistoricalDataTransformer
{
    /**
     * Transform historical fight data into a compact format.
     *
     * @param \Illuminate\Database\Eloquent\Collection $historicalFights
     * @return array
     */
    public function transformCollection($historicalFights): array
    {
        $data = [];
        foreach ($historicalFights as $hf) {
            $data[] = $this->transform($hf);
        }
        return $data;
    }

    /**
     * Transform a single historical fight record into a compact format.
     *
     * @param \App\Models\FHist $hf
     * @return array
     */
    public function transform(FHist $hf): array
    {
        return [
            'pool_id' => $hf->pool_id,
            'user1_address' => $hf->user1_address,
            'old_user1_balance' => $hf->old_user1_balance,
            'user1_balance' => $hf->user1_balance,
            'user1_battle_balance' => $hf->user1_battle_balance,
            'user1_premove_index' => $hf->user1_premove_index,
            'user1_move' => $hf->user1_move,
            'user1_gain' => $hf->user1_gain,
            'user2_address' => $hf->user2_address,
            'old_user2_balance' => $hf->old_user2_balance,
            'user2_balance' => $hf->user2_balance,
            'user2_battle_balance' => $hf->user2_battle_balance,
            'user2_premove_index' => $hf->user2_premove_index,
            'user2_move' => $hf->user2_move,
            'user2_gain' => $hf->user2_gain,
        ];
    }
}
