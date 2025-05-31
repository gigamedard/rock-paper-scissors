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
            // Convert the JSON data to a string
            $jsonContent = json_encode($data);

            // Upload the JSON data to Pinata
            $response = $this->client->post('pinning/pinJSONToIPFS', [
                'json' => [
                    'pinataContent' => $data, // The JSON data to upload
                    'pinataMetadata' => [
                        'name' => $fileName, // Optional: Name for the file
                    ],
                ],
            ]);

            // Get the CID of the uploaded file
            $cid = json_decode($response->getBody(), true)['IpfsHash'];

            return [
                'success' => true,
                'cid' => $cid,
            ];
        } catch (\Exception $e) {
            // Log the error
            Log::error('Failed to upload JSON to Pinata: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to upload JSON to Pinata.',
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
            // Construct the IPFS gateway URL
            $gatewayUrl = "https://gateway.pinata.cloud/ipfs/$cid";

            // Fetch the JSON data
            $response = file_get_contents($gatewayUrl);

            // Decode the JSON data
            return json_decode($response, true);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Failed to retrieve JSON from Pinata: ' . $e->getMessage());

            return null;
        }
    }
}