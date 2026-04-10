<?php

namespace App\Events;

use App\Models\Fight;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FightStartedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $opponent;
    public $fight;

    public function __construct(User $user, User $opponent, Fight $fight)
    {
        $this->user = $user;
        $this->opponent = $opponent;
        $this->fight = $fight;
        \Illuminate\Support\Facades\Log::info("[BROADCAST] ⚔️ FightStartedEvent for User {$user->id} vs {$opponent->id}");
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }
}
