<?php

namespace App\Services;

use App\Models\Fight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoMatchService
{
    public function processAutoMatch($betAmount, $instanceNumber, $limit = 10)
    {
        DB::transaction(function () use ($betAmount, $instanceNumber, $limit) {
            $sliceData = DB::table('slice_table')
                ->where('instance_number', $instanceNumber)
                ->where('bet_amount', $betAmount)
                ->where('current_instance', true)
                ->first();

            if (!$sliceData) {
                return;
            }

            $lastUserId = $sliceData->last_user_id ?? 0;

            $users = User::where('autoplay_active', true)
                ->where('bet_amount', $betAmount)
                ->where('id', '>', $lastUserId)
                ->where('status', 'available')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($users->count() % 2 !== 0) {
                $users->pop();
            }

            if ($users->isNotEmpty()) {
                $newLastUserId = $users->last()->id;
                DB::table('slice_table')
                    ->where('instance_number', $instanceNumber)
                    ->update(['last_user_id' => $newLastUserId]);

                for ($i = 0; $i < $users->count(); $i += 2) {
                    if (isset($users[$i + 1])) {
                        $fight = Fight::create([
                            'pool_id' => null,
                            'user1_id' => $users[$i]->id,
                            'user2_id' => $users[$i + 1]->id,
                            'base_bet_amount' => $betAmount,
                            'status' => 'waiting_for_result',
                        ]);
                        $fight->handleAutoplayFight();
                    }
                }
            }

            DB::table('slice_table')
                ->where('instance_number', $instanceNumber)
                ->where('bet_amount', $betAmount)
                ->increment('depth');
        });
    }

    public function selectSliceInstence($betAmount)
    {
        $depthLimit = config('game_settings.depth_limit');

        DB::transaction(function () use ($betAmount, $depthLimit) {
            $currentInstance = DB::table('slice_table')
                ->where('current_instance', true)
                ->where('bet_amount', $betAmount)
                ->first();

            if (!$currentInstance) {
                return;
            }

            if ($currentInstance->depth >= $depthLimit) {
                $outdatedInstance = DB::table('slice_table')
                    ->where('instance_number', '!=', $currentInstance->instance_number)
                    ->where('bet_amount', $betAmount)
                    ->orderBy('updated_at', 'asc')
                    ->first();

                if ($outdatedInstance) {
                    DB::table('slice_table')
                        ->where('id', $currentInstance->id)
                        ->update(['current_instance' => false]);

                    DB::table('slice_table')
                        ->where('id', $outdatedInstance->id)
                        ->update(['current_instance' => true, 'depth' => 0]);
                }
            }

            $this->processAutoMatch($betAmount, $currentInstance->instance_number);
        });
    }

    public function selectSliceInstenceForAllBetAmount()
    {
        $betAmounts = config('game_settings.bet_amounts');

        foreach ($betAmounts as $betAmount) {
            $this->selectSliceInstence($betAmount);
        }
    }
}
