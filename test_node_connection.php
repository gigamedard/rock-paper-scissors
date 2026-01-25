<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Mock a user with a valid token? 
// The route receives a `token.auth` middleware. This makes it hard to test without a valid token.
// However, the error logged by the user might be in `laravel.log` if it was a 500 error.
// The user previously saw 503. Now they succumb to the catch block.

// Let's try to hit the Node server DIRECTLY from here to see if THAT works.
$nodeUrl = 'http://127.0.0.1:3000/get-game-config';
$secret = '0x7c852118294e51e653712a81e05800f419141751be58f605c371e18990756086';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $nodeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Internal-Secret: $secret"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Node Response Code: $httpCode\n";
echo "Node Response Body: $response\n";
