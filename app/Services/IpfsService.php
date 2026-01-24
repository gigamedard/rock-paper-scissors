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
        try {
            // If data is an array/object, encode it. If it's a string, try to decode/encode to ensure it's valid JSON
            // or just pass it if we assume it's pre-formatted.
            // Best practice: accept array and encode it here.
            $jsonContent = is_string($data) ? $data : json_encode($data);

            // Using the IPFS 'add' endpoint
            // The file needs to be sent as multipart/form-data
            $response = Http::attach(
                'file', $jsonContent, 'data.json'
            )->post("{$this->nodeUrl}/api/v0/add", [
                'pin' => 'true' // Optional: Pin it immediately
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $cid = $result['Hash'] ?? null;
                Log::info("IPFS Upload Success. CID: {$cid}");
                return $cid;
            } else {
                Log::error("IPFS Upload Failed: " . $response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error("IPFS Service Exception: " . $e->getMessage());
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
