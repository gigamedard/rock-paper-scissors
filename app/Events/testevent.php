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



class testevent implements shouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     * 
     */

    public $balance;
    public $userId;

    public function __construct($userId,$balance)
    {
        $this->balance = $balance;
        //$this->user = Auth::user(); do not forget to un comment this line once the test is over

        $this->userId = $userId;// using this line of code for testing purpose
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {   
        return [
            //new PrivateChannel('App.Models.User.'.$this->user->id/*`challengechannel.{$this->challenge->id}`*/),
            new PrivateChannel('App.Models.User.'.$this->userId/*`challengechannel.{$this->challenge->id}`*/),
        ];
/*
        return [
            new PrivateChannel(`App.Models.User.{$this->user->id}`),
        ];*/
    }

    public function broadcastWith(): array
    {
        return ['balance'=>$this->balance];
    }
}
