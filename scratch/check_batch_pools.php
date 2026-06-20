<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Batch;
use App\Models\Pool;

$batch = Batch::find(177);
if (!$batch) {
    echo "Batch 177 not found.\n";
    exit;
}

echo "Batch 177 Details:\n";
echo "Status: {$batch->status}\n";
echo "Base Bet: {$batch->base_bet}\n";
echo "Pool Size: {$batch->pool_size}\n";
echo "First Pool ID: {$batch->first_pool_id}\n";
echo "Last Pool ID: {$batch->last_pool_id}\n";
echo "Number of Pools: {$batch->number_of_pools}\n";
echo "Max Size: {$batch->max_size}\n";

$pools = Pool::whereBetween('id', [$batch->first_pool_id, $batch->last_pool_id])
    ->where('base_bet', $batch->base_bet)
    ->where('pool_size', $batch->pool_size)
    ->get();

echo "\nPools in range [{$batch->first_pool_id}, {$batch->last_pool_id}]:\n";
foreach ($pools as $p) {
    echo "Pool ID: {$p->id} | Status: {$p->status} | Users: " . json_encode($p->users->pluck('id')) . "\n";
}
