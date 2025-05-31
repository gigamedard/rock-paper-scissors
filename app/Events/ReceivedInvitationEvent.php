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
use Auth;

class ReceivedInvitationEvent implements shouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $challenge;
    public $receivingUser; 
    public $senderName;

    public function __construct(User $paramUser,Challenge $paramChallenge, $paramSenderName)
    {  
        $this->challenge = $paramChallenge;
        $this->receivingUser = $paramUser;
        $this->senderName = $paramSenderName;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {   
        
         $channel = 'App.Models.User.' . $this->receivingUser->id;

        return [
            new PrivateChannel($channel),
        ];

    }

    public function broadcastWith(): array
    {   
        
        return  ['challenge' => $this->challenge->toArray(),
                'sender'=>$this->senderName,
                'receiver'=>$this->receivingUser->toArray()
                ];
    }
}
