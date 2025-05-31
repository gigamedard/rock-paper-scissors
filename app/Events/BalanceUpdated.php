<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\User;
use Auth;

class BalanceUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $balance;

   

    public function __construct($userId,$balance)
    {  
        $this->userId= $userId;
        $this->balance= $balance;
    }

    public function broadcastOn(): array
    {   
        
         $channel = 'App.Models.User.'.$this->userId;

         
        
        return [
            new PrivateChannel($channel),
        ];

    }

    public function broadcastWith(): array
    {   
        
        return  ['userId' => $this->userId, 'balance'=>$this->balance];
    }
}
