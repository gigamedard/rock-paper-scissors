<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\BasicEvent;
use App\Listeners\BasicEventListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    public function boot(): void
    {
        Event::listen(BasicEvent::class, BasicEventListener::class);
    }
}
