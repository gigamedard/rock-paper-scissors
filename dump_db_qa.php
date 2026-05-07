<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$users = App\Models\User::all(['id', 'wallet_address', 'balance', 'battle_balance', 'status', 'pool_id', 'bet_amount', 'session_started', 'autoplay_active']);
file_put_contents('users_dump.json', json_encode($users, JSON_PRETTY_PRINT));
echo "Dumped " . count($users) . " users to users_dump.json\n";

$pools = App\Models\Pool::all(['id', 'pool_id', 'status', 'base_bet', 'pool_size']);
file_put_contents('pools_dump.json', json_encode($pools, JSON_PRETTY_PRINT));
echo "Dumped " . count($pools) . " pools to pools_dump.json\n";

$batches = App\Models\Batch::all(['id', 'status', 'iteration_count', 'max_iterations', 'base_bet', 'pool_size']);
file_put_contents('batches_dump.json', json_encode($batches, JSON_PRETTY_PRINT));
echo "Dumped " . count($batches) . " batches to batches_dump.json\n";
