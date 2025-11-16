<?php

namespace App\Listeners;

use App\Events\SessionFinishedEvent;
use App\Models\User;
use App\Models\FHist;
use Illuminate\Support\Facades\Log;
use App\Helpers\Web3Helper;
use App\Services\PinataService;

class SessionFinishedEventListener
{
    protected $web3Helper;
    protected $pinataService;

    public function __construct(Web3Helper $web3Helper, PinataService $pinataService)
    {
        $this->web3Helper = $web3Helper;
        $this->pinataService = $pinataService;
    }

    /**
     * Handle the event.
     */
    public function handle(SessionFinishedEvent $event): void
    {
        try {
            $user = User::find($event->userId);
            if (!$user || !$user->preMove) {
                return;
            }

            $q = $this->calculateQValue($user);
            Log::info("Calculated q: $q for User: {$user->id}");

            $this->processUserBalance($user, $q);
        } catch (\Exception $e) {
            Log::error("SessionFinishedEventListener: Exception occurred for user ID: {$event->userId} - " . $e->getMessage());
        }
    }

    private function calculateQValue(User $user): float
    {
        $initialTotal = $user->session_start_balance + $user->session_start_battle_balance;
        $finalTotal = $user->balance + $user->battle_balance;
        return $finalTotal / ($initialTotal ?: 1);
    }

    private function processUserBalance(User $user, float $q): void
    {
        if ($q >= config('game_settings.gain_coefficient')) {
            $this->transferBattleBalance($user);
            $this->archiveSessionHistory($user);
            $this->sendPayment($user);
        } elseif ($q < 1 && $user->balance < $user->bet_amount) {
            // TODO: event(new UseAssurenceEvent($user->id));
            Log::info("SessionFinishedEventListener: User ID: {$user->id} triggered UseAssurenceEvent.");
        }
    }

    private function transferBattleBalance(User $user): void
    {
        $user->balance += $user->battle_balance;
        $user->pool_id = null;
        $user->battle_balance = 0;
        $user->bet_amount = 0;
        $user->preMove->current_index = 0;
        $user->status = 'available';
        $user->session_started = false;
        $user->session_start_balance = 0;
        $user->session_start_battle_balance = 0;
        $user->save();
    }

    private function archiveSessionHistory(User $user): void
    {
        $data = $this->getSessionFHists($user->id, $user->preMove->session_first_pool_id);
        if (empty($data)) {
            return;
        }

        $cid = $this->pinataService->pinJsonData(json_encode($data));
        if (!$cid) {
            Log::error("SessionFinishedEventListener: Failed to send session FHists to Pinata for User ID: {$user->id}");
            return;
        }

        $this->web3Helper->sendSessionCIDToSmartContract(env('NODE_URL'), $cid, $user->wallet_address);
    }

    private function getSessionFHists($userId, $fstPoolId)
    {
        $fHistInitial = FHist::where(function ($query) use ($userId) {
            $query->where('user1_id', $userId)->orWhere('user2_id', $userId);
        })
            ->where('pool_id', $fstPoolId)
            ->orderBy('pool_id', 'asc')
            ->first();

        if (!$fHistInitial) {
            return [];
        }

        return FHist::where(function ($query) use ($userId) {
            $query->where('user1_id', $userId)->orWhere('user2_id', '>=', $fHistInitial->id);
        })->get();
    }

    private function sendPayment(User $user): void
    {
        $this->web3Helper->sendPayement(env('NODE_URL'), $user->wallet_address, $user->balance);
    }
}
