<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lot d'événements (combats + fins de session) envoyé au front en une seule fois
 * toutes les N pools, pour limiter les appels WebSocket. Le front les rejoue
 * séquentiellement.
 */
class GameEventsBatch implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $events;

    public function __construct(public User $user, array $events)
    {
        $this->events = array_values($events);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->user->id)];
    }

    public function broadcastAs(): string
    {
        return 'GameEventsBatch';
    }

    public function broadcastWith(): array
    {
        return ['events' => $this->events];
    }
}
