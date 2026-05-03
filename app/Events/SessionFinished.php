<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionFinished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $reason; // "SUCCESS", "RUIN", "STRATEGIC_LIMIT"
    public $final_q;
    public $payout_triggered;
    public $signature;

    public function __construct(User $user, string $reason, string $final_q, bool $payout_triggered, ?string $signature = null)
    {
        $this->user = $user;
        $this->reason = $reason;
        $this->final_q = $final_q;
        $this->payout_triggered = $payout_triggered;
        $this->signature = $signature;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }
}
