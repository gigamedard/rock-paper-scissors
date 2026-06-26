<?php
$apps = \App\Models\InfluencerApplication::where('status', 'approved')->get();
foreach($apps as $app) {
    dump('App ID: ' . $app->id . ' | User ID: ' . $app->user_id . ' | Pseudo: ' . $app->pseudo);
}
