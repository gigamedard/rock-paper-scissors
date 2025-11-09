<?php

namespace App\Providers;

// IMPORTANT: Assure-toi d'étendre la classe de base de Laravel
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// --- Tes Événements Existants ---
use App\Events\BasicEvent;
use App\Listeners\BasicEventListener;
use App\Events\PoolFinishedEvent;
use App\Listeners\PoolFinishedEventListener;
use App\Events\SessionFinishedEvent;
use App\Listeners\SessionFinishedEventListener;

// --- NOTRE NOUVEL AJOUT (PROPOSITION 1) ---
use App\Events\FightCreatedEvent;
use App\Listeners\FightCreatedEventListener;


class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        
        // Ton événement de base
        BasicEvent::class => [
            BasicEventListener::class,
        ],

        // Les événements de ton projet (il vaut mieux les lister ici)
        PoolFinishedEvent::class => [
            PoolFinishedEventListener::class,
        ],
        SessionFinishedEvent::class => [
            SessionFinishedEventListener::class,
        ],

        // --- LA NOUVELLE LIGNE POUR LA PROPOSITION 1 ---
        FightCreatedEvent::class => [
            FightCreatedEventListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // On n'a plus besoin de rien ici, tout est géré par le tableau $listen
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        // C'est une bonne pratique de le mettre à false
        // quand tu définis tout manuellement dans $listen.
        return false;
    }
}