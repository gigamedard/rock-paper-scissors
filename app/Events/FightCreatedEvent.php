<?php
namespace App\Events;

use App\Models\Fight;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FightCreatedEvent
{
    use Dispatchable, SerializesModels;

    public Fight $fight;

    public function __construct(Fight $fight)
    {
        $this->fight = $fight;
    }
}