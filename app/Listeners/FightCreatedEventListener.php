<?php
namespace App\Listeners;

use App\Events\FightCreatedEvent;
use App\Services\FightService;
use Illuminate\Contracts\Queue\ShouldQueue; // Pour le futur
use Illuminate\Support\Facades\Log;

// Implémente ShouldQueue si tu veux que les combats
// soient traités en arrière-plan (recommandé)
class FightCreatedEventListener // implements ShouldQueue
{
    protected FightService $fightService;

    public function __construct(FightService $fightService)
    {
        $this->fightService = $fightService;
    }

    public function handle(FightCreatedEvent $event): void
    {
        Log::info("FightCreatedEventListener: Réception du combat ID {$event->fight->id}");
        
        try {
            $this->fightService->handleFight($event->fight);
        } catch (\Exception $e) {
            Log::error("FightCreatedEventListener: Échec du handleFight pour {$event->fight->id}: " . $e->getMessage());
        }
    }
}