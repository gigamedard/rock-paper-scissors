<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\GameSetting;

foreach (GameSetting::all() as $s) {
    echo "{$s->key} = {$s->value}\n";
}
