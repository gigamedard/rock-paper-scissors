<?php

namespace App\Traits;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

trait IPFSTrait
{
    private $client;

    public function __construct()
    {
        // Initialize Guzzle client for Pinata API
        $this->client = new Client([
            'base_uri' => 'https://api.pinata.cloud/',
            'headers' => [
                'pinata_api_key' => env('PINATA_API_KEY'),
                'pinata_secret_api_key' => env('PINATA_SECRET_API_KEY'),
            ],
        ]);
    }

    /**
     * Upload JSON data to Pinata.
     *
     * @param array $data
     * @param string $fileName
     * @return array
     */
    public function uploadJsonToPinata(array $data, string $fileName = 'poolHistory.json')
    {
        try {
            $ipfsService = app(\App\Services\IpfsService::class);
            $cid = $ipfsService->uploadJson($data);

            if ($cid) {
                return [
                    'success' => true,
                    'cid' => $cid,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to upload JSON to IPFS.',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload JSON to IPFS: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to upload JSON to IPFS.',
            ];
        }
    }

    /**
     * Retrieve JSON data from Pinata.
     *
     * @param string $cid
     * @return array|null
     */
    public function retrieveJsonFromPinata($cid)
    {
        try {
            $ipfsService = app(\App\Services\IpfsService::class);
            $data = $ipfsService->retrieveJson($cid);

            if ($data) {
                return $data;
            }

            Log::error('Failed to retrieve JSON from IPFS for CID: ' . $cid);
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve JSON from IPFS: ' . $e->getMessage());
            return null;
        }
    }
}