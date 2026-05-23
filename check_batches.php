<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$batches = App\Models\Batch::all();
echo "Batch Count: " . $batches->count() . PHP_EOL;
foreach($batches as $b) {
    echo "ID: {$b->id} | Status: {$b->status} | Bet: {$b->base_bet} | Size: {$b->pool_size} | Pools Count: {$b->number_of_pools} | Max Size: {$b->max_size}" . PHP_EOL;
}
