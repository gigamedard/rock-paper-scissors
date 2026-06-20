<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Events\FightResult;

$user = User::find(1);
if (!$user) {
    echo "User 1 not found.\n";
    exit;
}

echo "Broadcasting FightResult test event to User 1 via channel: App.Models.User.1\n";
echo "Broadcast driver: " . config('broadcasting.default') . "\n";

// Send a test FightResult event
event(new FightResult($user, 'win', '+0.02', (string)$user->balance, 'rock', 'scissors'));

echo "Event dispatched! Check browser console for 'game:fightResult'.\n";
echo "User balance: {$user->balance}\n";
