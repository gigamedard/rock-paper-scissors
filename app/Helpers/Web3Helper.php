<?php

namespace App\Helpers;

use kornrunner\Keccak;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
            $user = (substr($user, 0, 2) === '0x') ? substr($user, 2) : $user;
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
            $saltNoPrefix = (substr($salt, 0, 2) === '0x') ? substr($salt, 2) : $salt;

            // Concatenate the address (without "0x") with the salt.
            $combinedHex = $userNoPrefix . $saltNoPrefix;

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

    public static function etherToWei($eth)
    {
        return bcmul($eth, '1000000000000000000', 0); // 1 Ether = 10^18 Wei
    }
    // send achive to ipfs
    public static function sendArchiveToPinata($data)
    {   
        Log::info('IPFS request (via Web3Helper): ' . json_encode($data));
        
        // Resolve IpfsService from container
        $ipfsService = app(\App\Services\IpfsService::class);
        
        $cid = $ipfsService->uploadJson($data);

        Log::info('IPFS response CID: ' . $cid);

        if (!$cid) {
             Log::error('IPFS upload failed.');
             return '';
        }
        
        return $cid;
    }

    public static function sendPoolCIDToSmartContract($nodeUrl,$CID,$poolId)
    {
        $response = Http::post("{$nodeUrl}/sendPoolCID", [
            'poolId' => $poolId,
            'CID' => $CID,
        ]);

        return $response->json();
    }
    
    public static function sendSessionCIDToSmartContract($nodeUrl,$CID,$walletAddress)
    {
        $response = Http::post("{$nodeUrl}/sendSessionCID", [
            'wallet' => $walletAddress,
            'CID' => $CID,
        ]);

        return $response->json();
    }

    public static function sendPayement($nodeUrl,$walletAddress,$amount)
    {
        $response = Http::post("{$nodeUrl}/sendPayment", [
            'wallet' => $walletAddress,
            'amount' => self::etherToWei($amount),
        ]);

        return $response->json();
    }

    public static function premoveExists($userId)
    {
        // Check if the preMove exists in the database
        $preMove = DB::table('pre_moves')->where('user_id', $userId)->first();
        return $preMove !== null;
    }

    public static function marker($userId, $position1, $position2, $position3 = null)
    {
        if (self::premoveExists($userId)) {
            Log::info("...................................".$position1.".......". $position2."......". $position3." ....................................................................");
        } else {
            Log::info("XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX");
            Log::info("XXXXXXXXXXXX".$position1."  XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX");
            Log::info("XXXXXXXXXXXXXXXXXXXXXXXXXXXXX  ".$position2."  XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX");
            Log::info("XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX");
            Log::info("XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX  ".$position3."  XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX");
            Log::info("XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX");
        }
        

    }

    public static function sendBatchPayment($nodeUrl, array $walletAddresses, array $amounts)
    {
        // Validate that both arrays have the same length
        if (count($walletAddresses) !== count($amounts)) {
            return ['error' => 'Mismatched wallets and amounts'];
        }

        // Make an HTTP request to your Node.js backend
        $response = Http::post("{$nodeUrl}/sendBatchPayment", [
            'wallets' => $walletAddresses,
            'amounts' => $amounts,
        ]);

        return $response->json();
}

    public static function getPoolUsers($nodeUrl, $baseBet)
    {
        $response = Http::get("{$nodeUrl}/pool/users/{$baseBet}");
        return $response->json();
    }

    public static function getPremoveCID($nodeUrl, $walletAddress)
    {
        $response = Http::get("{$nodeUrl}/pool/premove/{$walletAddress}");
        return $response->json();
    }

    public static function refundUsers($nodeUrl, array $walletAddresses)
    {
        $response = Http::post("{$nodeUrl}/refundUsers", [
            'wallets' => $walletAddresses,
        ]);
        return $response->json();
    }

    public static function validatePool($nodeUrl, $baseBet)
    {
        $response = Http::post("{$nodeUrl}/pool/validate", [
            'baseBet' => $baseBet,
        ]);
        return $response->json();
    }

    public static function invalidatePoolUsers($nodeUrl, $baseBet, array $invalidWalletAddresses)
    {
        $response = Http::post("{$nodeUrl}/pool/invalidate", [
            'baseBet' => $baseBet,
            'invalidUsers' => $invalidWalletAddresses,
        ]);
        return $response->json();
    }

    public static function setUserNextSessionTime($nodeUrl, $walletAddress, $nextTime)
    {
        $response = Http::post("{$nodeUrl}/setUserNextSessionTime", [
            'wallet' => $walletAddress,
            'nextTime' => $nextTime,
        ]);

        return $response->json();
    }












    public static function getUserNonce($nodeUrl, $walletAddress)
    {
        $response = Http::get("{$nodeUrl}/getUserNonce/{$walletAddress}");
        $data = $response->json();
        return isset($data['nonce']) ? $data['nonce'] : 0;
    }
}
