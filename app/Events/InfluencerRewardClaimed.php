<?php

namespace App\Events;

use App\Models\Influencer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InfluencerRewardClaimed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $influencer;
    public $amount;

    /**
     * Create a new event instance.
     */
    public function __construct(Influencer $influencer, float $amount)
    {
        $this->influencer = $influencer;
        $this->amount = $amount;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}

