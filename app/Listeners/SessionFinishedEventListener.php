<?php

namespace App\Listeners;

use App\Events\SessionFinishedEvent;
use App\Models\User;
use App\Models\FHist;
use Illuminate\Support\Facades\Log;
use App\Helpers\Web3Helper;

class SessionFinishedEventListener
{
    /**
     * Handle the event.
     */
    public function handle(SessionFinishedEvent $event): void
    {   
        try {
            $user = $this->getUser($event->userId);
            if (!$user || !$user->preMove) return;

            $fstPoolId = $user->preMove->session_first_pool_id;
            Log::info("SessionFinishedEventListener: User ID: {$user->id}, first Pool ID: $fstPoolId");

            [$fHistInitial, $fHistFinal] = $this->getSessionEdgesFHists($user->id, $fstPoolId);
            if (!$fHistInitial || !$fHistFinal) return;

            if ($fHistInitial->old_balance == 0) {
                Log::warning("SessionFinishedEventListener: Initial balance is 0 for user ID: {$user->id}, Pool ID: $fstPoolId");
                return;
            }

            $q = $this->calculateQValue($fHistInitial, $fHistFinal);
            $this->processUserBalance($user, $q);
        } catch (\Exception $e) {
            Log::error("SessionFinishedEventListener: Exception occurred for user ID: {$event->userId} - " . $e->getMessage());
        }
    }

    private function getUser(int $userId): ?User
    {
        $user = User::find($userId);
        if (!$user) {
            Log::error("SessionFinishedEventListener: User not found for ID: $userId");
        }
        return $user;
    }

    private function getSessionEdgesFHists(int $userId, int $fstPoolId): array
    {
        $fHistInitial = FHist::where(fn($query) => $query->where('user1_id', $userId)->orWhere('user2_id', $userId))
            ->where('pool_id', $fstPoolId)
            ->orderBy('pool_id', 'asc')
            ->first();

        $fHistFinal = FHist::where(fn($query) => $query->where('user1_id', $userId)->orWhere('user2_id', $userId))
            ->orderBy('pool_id', 'desc')
            ->first();

        if (!$fHistInitial || !$fHistFinal) {
            Log::error("SessionFinishedEventListener: Historical data missing for pool ID: $fstPoolId, User ID: $userId");
        }

        return [$fHistInitial, $fHistFinal];
    }

    private function getSessionFHists($userId, $fstPoolId)
    {
        $fHistInitial = FHist::where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)
                      ->orWhere('user2_id', $userId);
            })
            ->where('pool_id', $fstPoolId)
            ->orderBy('pool_id', 'asc')
            ->first();

        $sessionfHist = FHist::where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)
                      ->orWhere('user2_id', $userId);
            })
            ->where('id','>=', $fstfHistInitial->id)
            ->get();

        return userSessionfHist;
    }

    private function calculateQValue($fHistInitial, $fHistFinal): float
    {
        return $fHistFinal->balance / $fHistInitial->old_balance;
    }

    private function processUserBalance(User $user, float $q): void
    {
        if ($q >= 2) { 
            $this->transferBattleBalance($user);
            //send sessionFHists to pinata
            $data = $this->getSessionFHists($user->id, $user->preMove->session_first_pool_id);

            Log::info('SessionFinishedEventListener: Sending session FHists to Pinata: ' . json_encode($data));
            
            $cid = Web3Helper::sendArchiveToPinata($data);
            //send cid to smart contract
            Web3Helper::sendSessionCIDToSmartContract(env('NODE_URL'), $cid, $user->wallet_address); 


            $this->sendPayment($user);
        } elseif ($q < 1 && $user->balance < $user->bet_amount) {
            // TODO: event(new UseAssurenceEvent($user->id));
            Log::info("SessionFinishedEventListener: User ID: {$user->id} triggered UseAssurenceEvent.");
        }
    }

    private function transferBattleBalance(User $user): void
    {
        $user->balance += $user->battle_balance;
        $user->battle_balance = 0;
        $user->bet_amount = 0;
        $user->preMove->current_index = 0;
        $user->status = 'available';
        $user->save();

        Log::info("SessionFinishedEventListener: User ID: {$user->id} successfully transferred battle balance and reset status.");
    }

    private function sendPayment(User $user): void
    {
        $nodeUrl = env('NODE_URL');
        Web3Helper::sendPayement($nodeUrl, $user->wallet_address, $user->balance);
    }
}
