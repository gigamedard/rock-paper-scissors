<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$batches = App\Models\Batch::all(['id', 'status', 'first_pool_id', 'last_pool_id', 'base_bet', 'pool_size']);
file_put_contents('batches_dump_detailed.json', json_encode($batches, JSON_PRETTY_PRINT));
echo "Dumped " . count($batches) . " detailed batches to batches_dump_detailed.json\n";
