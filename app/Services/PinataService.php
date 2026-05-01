<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PinataService
{
    /**
     * Upload a JSON string to Pinata and return the resulting CID.
     */
    public function pinJsonData(string $jsonData): ?string
    {
        try {
            $response = Http::withHeaders([
                'pinata_api_key' => env('PINATA_API_KEY'),
                'pinata_secret_api_key' => env('PINATA_SECRET_API_KEY'),
            ])->post('https://api.pinata.cloud/pinning/pinJSONToIPFS', [
                'pinataContent' => json_decode($jsonData, true),
            ]);

            return $response->json('IpfsHash');
        } catch (\Exception $e) {
            Log::error('Failed to upload JSON to Pinata: ' . $e->getMessage());
            return null;
        }
    }
}
