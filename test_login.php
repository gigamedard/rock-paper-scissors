<?php
$url = 'http://127.0.0.1:8001/api/login';
$data = ['wallet_address' => '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266'];

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "Status: " . $http_response_header[0] . "\n";
echo "Response: " . $result . "\n";
