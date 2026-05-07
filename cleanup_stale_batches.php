<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Batch;
use App\Models\Pool;
use App\Services\BatchProcessing\PoolFetcherService;

$poolFetcher = app(PoolFetcherService::class);

$batches = Batch::where('status', 'waiting')->get();

foreach ($batches as $batch) {
    $pools = $poolFetcher->fetchPoolsForProcessing($batch);
    if ($pools->isEmpty()) {
        echo "Batch {$batch->id} (Tier {$batch->base_bet}) has no processable pools in range [{$batch->first_pool_id}, {$batch->last_pool_id}]. Marking as SETTLED.\n";
        $batch->status = 'settled';
        $batch->save();
    } else {
        echo "Batch {$batch->id} (Tier {$batch->base_bet}) still has " . $pools->count() . " pools to process.\n";
    }
}

echo "Cleanup complete.\n";
