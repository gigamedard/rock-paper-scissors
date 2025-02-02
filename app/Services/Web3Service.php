<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use kornrunner\Keccak;

class Web3Service
{
    /**
     * Generate a Keccak-256 hash for an array of user addresses.
     *
     * @param array $users Array of addresses (e.g. ["0xabc...", "0xdef...", ...])
     * @return string The hexadecimal hash.
     */
    public function generateSalt(array $users): string
    {
        $concatenatedAddresses = '';

        foreach ($users as $user) {
            // Remove the '0x' prefix if present.
            if (Str::startsWith($user, '0x')) {
                $user = substr($user, 2);
            }
            $concatenatedAddresses .= $user;
        }

        Log::info('concatenatedAddresses: ' . $concatenatedAddresses);

        // Convert the concatenated hexadecimal string to binary data.
        $binaryData = hex2bin($concatenatedAddresses);
        if ($binaryData === false) {
            Log::error('Invalid hexadecimal string provided.');
            throw new \InvalidArgumentException('Invalid hexadecimal string provided.');
        }

        // Compute the Keccak-256 hash of the binary data.
        $hash = Keccak::hash($binaryData, 256);
        Log::info('hash: ' . $hash);

        return $hash;
    }

    /**
     * Sort an array of addresses by hashing each one combined with a salt and sorting the hashes.
     *
     * Steps:
     * 1. For each address, remove the "0x" prefix (if any).
     * 2. Concatenate the address (as a hex string without the "0x") with the provided salt.
     * 3. Convert the concatenated hex string to binary.
     * 4. Compute the Keccak-256 hash of the binary data.
     * 5. Sort the addresses based on the alphanumerical order of their hashes.
     *
     * @param array  $users Array of addresses.
     * @param string $salt  The salt as a hexadecimal string (without "0x" prefix).
     * @return array        The sorted addresses.
     */
    public function sortAddressesWithSalt(array $users, string $salt): array
    {
        $addressHashes = [];

        foreach ($users as $user) {
            // Remove the '0x' prefix if present.
            if (Str::startsWith($user, '0x')) {
                $userNoPrefix = substr($user, 2);
            } else {
                $userNoPrefix = $user;
            }

            // Combine the address with the salt.
            // You can choose the order of concatenation.
            // Here, we concatenate the address (without "0x") followed by the salt.
            $combinedHex = $userNoPrefix . $salt;

            // Convert the combined hex string to binary.
            $binaryCombined = hex2bin($combinedHex);
            if ($binaryCombined === false) {
                Log::error("Invalid hex combination for address {$user} and salt {$salt}");
                throw new \InvalidArgumentException("Invalid hex combination for address {$user} and salt {$salt}");
            }

            // Compute the Keccak-256 hash of the binary data.
            $hash = Keccak::hash($binaryCombined, 256);
            $addressHashes[$user] = $hash;
        }

        // Sort the addresses by the hash values (alphanumerically).
        asort($addressHashes, SORT_STRING);

        // Return the addresses in the sorted order.
        return array_keys($addressHashes);
    }
}
