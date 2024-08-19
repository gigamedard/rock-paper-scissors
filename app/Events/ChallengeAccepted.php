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

class ChallengeAccepted implements shouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $challengeId;
    public $senderId;
    public $fightId;
    public $createdAt; 
   

    public function __construct($senderId,$paramInvitationId,$paramFightId=0,$paramfightCreatedAt)
    {  
        $this->challengeId = $paramInvitationId;
        $this->senderId= $senderId;
        $this->fightId = $paramFightId;
        $this->createdAt = $paramfightCreatedAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {   
        
         $channel = 'App.Models.User.' . $this->senderId;
        
        return [
            new PrivateChannel($channel),
        ];

    }

    public function broadcastWith(): array
    {   
        
        return  ['invitationId' => $this->challengeId, 'fightId'=>$this->fightId, 'createdAt'=>$this->createdAt];
    }
}


