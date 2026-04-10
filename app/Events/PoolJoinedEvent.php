<?php

namespace App\Events;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolJoinedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $pool;

    public function __construct(User $user, Pool $pool)
    {
        $this->user = $user;
        $this->pool = $pool;
        \Illuminate\Support\Facades\Log::info("[BROADCAST] 🌍 PoolJoinedEvent for User {$user->id} in Pool {$pool->id}");
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }
}
