<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\Challenge;
use App\Models\User;



class testevent implements shouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     * 
     */

    public $challenge;
    public $user;

    public function __construct(/*User $paramUser,$paramChallenge*/)
    {
        /*$this->challenge = $paramChallenge;
        $this->user = $paramUser;*/
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {   
        return [
            new PrivateChannel('App.Models.User.1'/*`challengechannel.{$this->challenge->id}`*/),
        ];
/*
        return [
            new PrivateChannel(`App.Models.User.{$this->user->id}`),
        ];*/
    }

    public function broadcastWith(): array
    {
        return ['message'=>'GOD CODE ACTIVATED'];
    }
}
