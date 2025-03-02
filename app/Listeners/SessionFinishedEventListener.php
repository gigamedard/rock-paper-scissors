<?php

namespace App\Listeners;

use App\Events\SessionFinishedEvent;
use App\Models\User;
use App\Models\FHist;
use Illuminate\Support\Facades\Log;

class SessionFinishedEventListener
{
    /**
     * Handle the event.
     */
    public function handle(SessionFinishedEvent $event): void
    {   
        try {
            $userId = $event->userId;
            $user = User::find($userId);

            if (!$user) {
                Log::error("SessionFinishedEventListener: User not found for ID: $userId");
                return;
            }

            if (!$user->preMove) {
                Log::error("SessionFinishedEventListener: preMove not found for user ID: $userId");
                return;
            }

            $fstPoolId = $user->preMove->session_first_pool_id;
            log::info("SessionFinishedEventListener: User ID: $userId, first Pool ID: $fstPoolId");

            // Retrieve fHistInitial and fHistFinal ensuring the user is in user1_id or user2_id
            $fHistInitial = FHist::where(function ($query) use ($userId) {
                    $query->where('user1_id', $userId)
                          ->orWhere('user2_id', $userId);
                })
                ->where('pool_id', $fstPoolId)
                ->orderBy('pool_id', 'asc')
                ->first();

            $fHistFinal = FHist::where(function ($query) use ($userId) {
                    $query->where('user1_id', $userId)
                          ->orWhere('user2_id', $userId);
                })
                // Ensure it matches any other pool
                ->orderBy('pool_id', 'desc')
                ->first();

            if (!$fHistInitial || !$fHistFinal) {
                Log::error("SessionFinishedEventListener: Historical data missing for pool ID: $fstPoolId, User ID: $userId");
                return;
            }

            if ($fHistInitial->old_balance == 0) {
                Log::warning("SessionFinishedEventListener: Initial balance is 0 for user ID: $userId, Pool ID: $fstPoolId");
                return;
            }

            $q = $fHistFinal->balance / $fHistInitial->old_balance;

            if ($q >= 2) {// TODO: replace 2 with the user calculated value
                // Transfer battle balance to main balance
                $user->balance += $user->battle_balance;
                $user->battle_balance = 0;

                // Reset user betting and status
                $user->bet_amount = 0;
                $user->preMove->current_index = 0;
                $user->status = 'available';

                $user->save();

                Log::info("SessionFinishedEventListener: User ID: $userId successfully transferred battle balance and reset status.");

                // TODO: Implement blockchain transfer logic here

            } elseif ($q < 1 && $user->balance < $user->bet_amount) {
                // TODO: event(new UseAssurenceEvent($user->id));
                Log::info("SessionFinishedEventListener: User ID: $userId triggered UseAssurenceEvent.");
            }
        } catch (\Exception $e) {
            Log::error("SessionFinishedEventListener: Exception occurred for user ID: $userId - " . $e->getMessage());
        }
    }
}
