<?php

return [
    // Active le moteur mémoire à la place du MatchmakingEngine (Pool::match()).
    // Laisser false tant que le moteur n'a pas été validé sur le VPS.
    'enabled' => (bool) env('GAME_ENGINE_ENABLED', false),

    // Nombre de pools traitées entre deux flush d'événements (vers le front).
    'batch_pools' => (int) env('GAME_ENGINE_BATCH_POOLS', 20),

    // Intervalle du tick du moteur (secondes).
    'tick_seconds' => (float) env('GAME_ENGINE_TICK', 1.5),

    // Stream Redis d'audit brut des combats.
    'fight_stream' => env('GAME_ENGINE_FIGHT_STREAM', 'battlepool:fights'),
    'consumer_group' => env('GAME_ENGINE_CONSUMER_GROUP', 'battlepool-archive'),
    'consumer_name' => env('GAME_ENGINE_CONSUMER', gethostname() ?: 'worker'),

    // Taille max lue par le consumer d'audit.
    'archive_batch' => (int) env('GAME_ENGINE_ARCHIVE_BATCH', 1000),

    // Dossier d'archive JSONL (audit/équitabilité, en plus de la table fights).
    'archive_dir' => env('GAME_ENGINE_ARCHIVE_DIR', storage_path('app/fights-archive')),
];
