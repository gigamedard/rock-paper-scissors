<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pools = App\Models\Pool::all();
echo "Pool Count: " . $pools->count() . PHP_EOL;
foreach($pools as $p) {
    echo "ID: {$p->id} | Status: {$p->status} | Bet: {$p->base_bet} | Size: {$p->pool_size} | Users: " . json_encode($p->users->pluck('id')) . PHP_EOL;
}
