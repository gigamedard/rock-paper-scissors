<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpfsService
{
    protected $nodeUrl;
    protected $gatewayUrl;

    public function __construct()
    {
        // Default to local IPFS node if env not set
        $this->nodeUrl = rtrim(env('IPFS_NODE_URL', 'http://127.0.0.1:5001'), '/');
        $this->gatewayUrl = rtrim(env('IPFS_GATEWAY_URL', 'http://127.0.0.1:8080/ipfs'), '/');
    }

    /**
     * Upload JSON data to IPFS and return the resulting CID.
     *
     * @param mixed $data
     * @return string|null
     */
    public function uploadJson($data): ?string
    {
        // 1. Try Local IPFS (Node.js Worker with Helia)
        try {
            // Check if we have a custom internal node URL, otherwise default to the worker port 3000
            $nodeWorkerUrl = config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
            
            // Allow raw array/object to be sent as JSON body
            $payload = is_string($data) ? json_decode($data, true) : $data;
            if (is_null($payload) && is_string($data)) $payload = ['content' => $data]; // Fallback wrapper

            $response = Http::timeout(5)->post("{$nodeWorkerUrl}/ipfs/add-json", $payload);

            if ($response->successful()) {
                $result = $response->json();
                $cid = $result['Hash'] ?? null;
                Log::info("Local IPFS (Helia) Upload Success. CID: {$cid}");
                return $cid;
            } else {
                Log::error("Local IPFS (Helia) Error. Status: " . $response->status() . " Body: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning("Local IPFS (Helia) failed: " . $e->getMessage() . ". Falling back to Pinata.");
        }

        // 2. Fallback to Pinata
        return $this->uploadToPinata($data);
    }

    protected function uploadToPinata($data): ?string
    {
        try {
            $apiKey = env('PINATA_API_KEY');
            $apiSecret = env('PINATA_SECRET_API_KEY');
            // Or JWT if preferred
            $jwt = env('PINATA_JWT');

            Log::info("Attempting Pinata Fallback...");

            $url = 'https://api.pinata.cloud/pinning/pinJSONToIPFS';
            
            $payload = [
                'pinataContent' => $data,
                'pinataMetadata' => [
                    'name' => 'Backup-Upload-' . time()
                ]
            ];

            // Use JWT if available, else keys
            $headers = ['Content-Type' => 'application/json'];
            if ($jwt) {
                $headers['Authorization'] = "Bearer $jwt";
            } else {
                $headers['pinata_api_key'] = $apiKey;
                $headers['pinata_secret_api_key'] = $apiSecret;
            }

            $response = Http::withHeaders($headers)->post($url, $payload);

            if ($response->successful()) {
                $result = $response->json();
                $cid = $result['IpfsHash'] ?? null;
                Log::info("Pinata Fallback Success. CID: {$cid}");
                return $cid;
            } else {
                Log::error("Pinata Fallback Failed: " . $response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Pinata Fallback Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve JSON data from IPFS using the CID.
     *
     * @param string $cid
     * @return mixed
     */
    public function retrieveJson(string $cid)
    {
        try {
            $url = "{$this->gatewayUrl}/{$cid}";
            $response = Http::get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("IPFS Retrieve Failed for CID {$cid}: " . $response->status());
            return null;

        } catch (\Exception $e) {
            Log::error("IPFS Retrieve Exception for CID {$cid}: " . $e->getMessage());
            return null;
        }
    }
}
