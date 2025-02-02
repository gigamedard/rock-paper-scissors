<?php

namespace App\Helpers;

use kornrunner\Keccak;

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
}
