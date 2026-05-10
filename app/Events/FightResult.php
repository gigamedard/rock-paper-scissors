<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FightResult implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $result; // "win", "loss", "draw"
    public $delta;
    public $current_balance;
    public $my_move;
    public $opponent_move;

    public function __construct(User $user, string $result, string $delta, string $current_balance, string $my_move = 'rock', string $opponent_move = 'rock')
    {
        $this->user = $user;
        $this->result = $result;
        $this->delta = $delta;
        $this->current_balance = $current_balance;
        $this->my_move = $my_move;
        $this->opponent_move = $opponent_move;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FightResult';
    }
}
