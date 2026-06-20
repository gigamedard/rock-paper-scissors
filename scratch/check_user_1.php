<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::find(1);
if ($user) {
    echo json_encode($user->toArray(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "User 1 not found.\n";
}
