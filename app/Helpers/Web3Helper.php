<?php

namespace App\Helpers;

use kornrunner\Keccak;
use Illuminate\Support\Facades\Log;
class Web3Helper
{
    /**
     * Generate a Keccak-256 hash from an array of user addresses.
     *
     * @param array $users Array of addresses (e.g. ["0xabc...", "0xdef...", ...])
     * @return string The hexadecimal hash (salt).
     */
    public static function generateHash(array $users): string
    {
        $concatenatedAddresses = '';

        foreach ($users as $user) {
            // Remove the '0x' prefix if present.
            if (substr($user, 0, 2) === '0x') {
                $user = substr($user, 2);
            }
            $concatenatedAddresses .= $user;
        }

        // Convert concatenated hex string to binary data.
        $binaryData = hex2bin($concatenatedAddresses);
        if ($binaryData === false) {
            throw new \InvalidArgumentException('Invalid hexadecimal string provided.');
        }

        // Compute and return the Keccak-256 hash.
        return Keccak::hash($binaryData, 256);
    }

    /**
     * Sort an array of addresses by hashing each address combined with a salt.
     *
     * Steps:
     * 1. Remove the '0x' prefix from each address.
     * 2. Concatenate the address with the provided salt.
     * 3. Compute the Keccak-256 hash.
     * 4. Sort addresses alphanumerically by the hash.
     *
     * @param array  $users Array of addresses.
     * @param string $salt  The salt as a hexadecimal string (without "0x" prefix).
     * @return array        Sorted addresses.
     */
    public static function sortAddressesWithSalt(array $users, string $salt): array
    {
        $addressHashes = [];

        foreach ($users as $user) {
            $userNoPrefix = (substr($user, 0, 2) === '0x') ? substr($user, 2) : $user;

            // Concatenate the address (without "0x") with the salt.
            $combinedHex = $userNoPrefix . $salt;

            // Convert the combined hex string to binary.
            $binaryCombined = hex2bin($combinedHex);
            if ($binaryCombined === false) {
                throw new \InvalidArgumentException("Invalid hex combination for address {$user} and salt {$salt}");
            }

            // Compute the Keccak-256 hash.
            $hash = Keccak::hash($binaryCombined, 256);
            $addressHashes[$user] = $hash;
        }

        // Sort the addresses by the hash values alphanumerically.
        asort($addressHashes, SORT_STRING);

        // Return the addresses in sorted order.
        return array_keys($addressHashes);
    }

    public static function weiToEther($wei)
    {
        return bcdiv($wei, '1000000000000000000', 18); // 1 Ether = 10^18 Wei
    }

    // send achive to pinata
    public static function sendArchiveToPinata($data)
    {   
        Log::info('Pinata request: ' . json_encode($data));
        //log data type
        Log::info('Pinata request data type: ' . gettype($data));
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.pinata.cloud/pinning/pinJSONToIPFS');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'pinata_api_key: ' . env('PINATA_API_KEY');
        $headers[] = 'pinata_secret_api_key: ' . env('PINATA_SECRET_API_KEY');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $data = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        //{"cid":"{\"IpfsHash\":\"QmZJD8z11RdwcWWetFBaPD28GZ18zsaN18BjSBnXZnjYoU\",\"PinSize\":428,\"Timestamp\":\"2025-02-16T12:42:20.693Z\"}"}
        //return only the ipfsHash
        $result = json_decode($result, true);

        Log::info('Pinata response: ' . json_encode($result));
        
        return $result['IpfsHash'];
    }
}
