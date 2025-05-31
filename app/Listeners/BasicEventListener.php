<?php

namespace App\Listeners;

use App\Events\BasicEvent;
use Illuminate\Support\Facades\Log;

class BasicEventListener
{
    public function handle(BasicEvent $event)
    {
        Log::info("BasicEvent Triggered: " . $event->message);
    }
}

