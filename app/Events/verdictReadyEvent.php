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


use App\Models\Fight;
use App\Models\User;
use Auth;

class verdictReadyEvent implements shouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $fight;
    public $gain;
    public $verdict; 
   

    public function __construct(Fight $paramFight,$paramUserId,$paramGain, $paramVerdict)
    {  
        $this->userId= $paramUserId;
        $this->fight = $paramFight;

        $this->gain = $paramGain;
        $this->verdict = $paramVerdict; 
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {   
        
         $channel = 'App.Models.User.' . $this->userId;
        
        return [
            new PrivateChannel($channel),
        ];

    }

    public function broadcastWith(): array
    {   
        
        return  [
            'fight' => $this->fight,
         
            'gain'=>$this->gain,
            'verdict'=>$this->verdict
        ];
    }
}