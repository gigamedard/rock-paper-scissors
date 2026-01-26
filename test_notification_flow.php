<?php

use App\Models\User;
use App\Models\GameNotification;
use App\Models\Fight;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "---------------------------------------------------\n";
echo "Testing Notification System Flow\n";
echo "---------------------------------------------------\n";

// 1. Setup User
$user = User::first();
if (!$user) {
    $user = User::factory()->create(['wallet_address' => '0x' . bin2hex(random_bytes(20))]);
}
echo "Testing with User: {$user->id} ({$user->wallet_address})\n\n";

// Login the user for Auth::user() calls in Controller
Auth::login($user);

// 2. Clear previous notifications for clean test
GameNotification::where('user_id', $user->id)->delete();
echo "[SETUP] Cleared old notifications.\n";

// 3. Simulate Events (Direct Model Creation mimicking Controllers)
echo "[ACTION] Simulating 'POOL_JOINED' event...\n";
GameNotification::create([
    'user_id' => $user->id,
    'type' => 'POOL_JOINED',
    'data' => ['bet_amount' => 0.1, 'timestamp' => now()->toIso8601String()]
]);

echo "[ACTION] Simulating 'PAYOUT' event...\n";
GameNotification::create([
    'user_id' => $user->id,
    'type' => 'PAYOUT',
    'data' => ['amount' => 0.5, 'currency' => 'ETH', 'tx_hash' => '0x123...']
]);

// 4. Test Polling (Call Controller logic)
echo "\n[TEST] Polling API (NotificationController::poll)...\n";

$controller = new NotificationController();
$request = Request::create('/api/notifications/poll', 'GET');
$request->setUserResolver(function () use ($user) { return $user; }); // Mock auth

$response = $controller->poll($request);
$content = json_decode($response->getContent(), true);

// 5. Verify Response
$notifications = $content['notifications'];
echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Notifications Found: " . count($notifications) . "\n";

$passed = true;
if (count($notifications) !== 2) {
    echo "[FAIL] Expected 2 notifications, got " . count($notifications) . "\n";
    $passed = false;
} else {
    echo "[PASS] Count is correct.\n";
    if ($notifications[0]['type'] === 'POOL_JOINED') echo "[PASS] First notification is POOL_JOINED.\n";
    else { echo "[FAIL] First notification type mismatch.\n"; $passed = false; }
}

// 6. Verify Read Status (Should be marked read now)
echo "\n[TEST] Verifying Read Status in DB...\n";
$unreadCount = GameNotification::where('user_id', $user->id)->whereNull('read_at')->count();
if ($unreadCount === 0) {
    echo "[PASS] All notifications marked as read.\n";
} else {
    echo "[FAIL] {$unreadCount} notifications still unread.\n";
    $passed = false;
}

echo "---------------------------------------------------\n";
echo $passed ? "✅ TEST SUCCEEDED" : "❌ TEST FAILED";
echo "\n---------------------------------------------------\n";
