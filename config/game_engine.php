<?php

return [
    // Active le moteur mémoire à la place du MatchmakingEngine (Pool::match()).
    // Laisser false tant que le moteur n'a pas été validé sur le VPS.
    'enabled' => (bool) env('GAME_ENGINE_ENABLED', false),

    // Worker persistant dédié (php artisan game:engine). Quand true, Pool::match()
    // ne traite plus les pools (c'est le worker qui les consomme) → pas de double traitement.
    'worker' => (bool) env('GAME_ENGINE_WORKER', false),

    // Mode simulation : les bots ne déclenchent NI payout on-chain NI cooldown/notif.
    // Recommandé pour les runs de test (évite de solliciter l'operator on-chain).
    'simulation_no_payout' => (bool) env('GAME_ENGINE_SIM_NO_PAYOUT', false),

    // Décaler les effets de bord des HUMAINS dans l'outbox asynchrone (payout,
    // notifications, syncLimits, cooldown, broadcast fin de session). La boucle
    // de jeu ne les exécute plus → vitesse identique bots/humains.
    'defer_side_effects' => (bool) env('GAME_ENGINE_DEFER_SIDE_EFFECTS', false),

    // Nombre de pools traitées entre deux flush d'événements (vers le front).
    'batch_pools' => (int) env('GAME_ENGINE_BATCH_POOLS', 20),

    // Intervalle du tick du moteur (secondes).
    'tick_seconds' => (float) env('GAME_ENGINE_TICK', 1.5),

    // Stream Redis d'audit brut des combats.
    'fight_stream' => env('GAME_ENGINE_FIGHT_STREAM', 'battlepool:fights'),
    'consumer_group' => env('GAME_ENGINE_CONSUMER_GROUP', 'battlepool-archive'),
    'consumer_name' => env('GAME_ENGINE_CONSUMER', gethostname() ?: 'worker'),

    // Taille max lue par le consumer d'audit.
    'archive_batch' => (int) env('GAME_ENGINE_ARCHIVE_BATCH', 5000),

    // Nombre de batches d'audit par tick (rattrapage rapide du Stream).
    'archive_batches_per_tick' => (int) env('GAME_ENGINE_ARCHIVE_BATCHES', 4),

    // Dossier d'archive JSONL (audit/équitabilité, en plus de la table fights).
    'archive_dir' => env('GAME_ENGINE_ARCHIVE_DIR', storage_path('app/fights-archive')),

    // Moteur in-memory : inclure aussi les humains UI (autoplay=0) comme joueurs.
    // À utiliser À LA PLACE de engine + batch-processor.
    'sim_include_humans' => (bool) env('SIM_INCLUDE_HUMANS', false),
    'sim_human_refresh_seconds' => (float) env('SIM_HUMAN_REFRESH_SECONDS', 10),
];
