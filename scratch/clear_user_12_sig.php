<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::find(12);
if ($user) {
    $user->payout_signature = null;
    $user->balance = 0;
    $user->status = 'stopped';
    $user->save();
    echo "✅ Success: User 12 payout_signature set to null in database.\n";
} else {
    echo "User 12 not found.\n";
}
