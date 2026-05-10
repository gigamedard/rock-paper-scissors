<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalArenaEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $type;

    public function __construct($message, $type = 'info')
    {
        $this->message = $message;
        $this->type = $type;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('arena'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'BasicEvent';
    }
}
