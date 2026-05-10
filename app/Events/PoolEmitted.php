<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolEmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $poolId;
    public $wallets;
    public $amount;

    public function __construct(string $poolId, array $wallets, float $amount)
    {
        $this->poolId = $poolId;
        $this->wallets = $wallets;
        $this->amount = $amount;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('pools'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PoolEmitted';
    }
}
