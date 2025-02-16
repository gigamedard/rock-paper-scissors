<?php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PinataService
{
    /**
     * Upload a JSON string to Pinata and return the resulting CID.
     */
    public function pinJsonData(string $jsonData): ?string
    {
        $client = new Client([
            'base_uri' => 'https://api.pinata.cloud/',
            'headers'  => [
                'pinata_api_key'    => env('PINATA_API_KEY'),
                'pinata_secret_api_key' => env('PINATA_SECRET_KEY'),
            ],
        ]);

        try {
            $response = $client->post('pinning/pinJSONToIPFS', [
                'json' => [
                    'pinataContent' => json_decode($jsonData, true),
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            return $responseData['IpfsHash'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to upload JSON to Pinata: ' . $e->getMessage());
            return null;
        }
    }
}
