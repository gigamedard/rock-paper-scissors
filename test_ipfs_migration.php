<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ipfsService = app(\App\Services\IpfsService::class);

echo "---------------------------------------------------\n";
echo "Testing IPFS Service (Local Node -> Fallback Pinata)\n";
echo "---------------------------------------------------\n";

$data = [
    'test_timestamp' => time(),
    'message' => 'Verification of IPFS Migration'
];

echo "Attempting to upload data: " . json_encode($data) . "\n\n";

try {
    $cid = $ipfsService->uploadJson($data);
    
    if ($cid) {
        echo "[SUCCESS] Uploaded! CID: $cid\n";
        echo "Validating retrieval...\n";
        
        $retrieved = $ipfsService->retrieveJson($cid);
        if ($retrieved) {
             echo "[SUCCESS] Retrieved Data: " . json_encode($retrieved) . "\n";
             if ($retrieved['test_timestamp'] == $data['test_timestamp']) {
                 echo "[PASSED] Integrity Check OK.\n";
             } else {
                 echo "[FAILED] Data mismatch.\n";
             }
        } else {
            echo "[WARNING] Could not immediately retrieve data (might be propagation delay if using public gateway).\n";
        }
    } else {
        echo "[FAIL] Upload returned null.\n";
    }

} catch (\Exception $e) {
    echo "[ERROR] Exception: " . $e->getMessage() . "\n";
}

echo "---------------------------------------------------\n";
