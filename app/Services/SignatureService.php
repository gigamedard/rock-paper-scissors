<?php

namespace App\Services;

use Elliptic\EC;
use kornrunner\Keccak;

class SignatureService
{
    /**
     * Generates a signature for the claimAndExit function.
     * signature = sign(hash(userAddress, amount, nonce, contractAddress))
     */
    public function generateClaimSignature(string $userAddress, string $amountWei, int $nonce, string $contractAddress): string
    {
        $privateKey = env('GAME_WALLET_PK');
        if (str_starts_with($privateKey, '0x')) {
            $privateKey = substr($privateKey, 2);
        }

        // 1. Prepare data for hashing
        // We use the same format as abi.encodePacked in Solidity:
        // address (20 bytes), uint256 (32 bytes), uint256 (32 bytes), address (20 bytes)
        
        $data = $this->encodePacked([
            ['address', $userAddress],
            ['uint256', $amountWei],
            ['uint256', (string)$nonce],
            ['address', $contractAddress]
        ]);

        // 2. Keccak256 hash
        $hash = Keccak::hash(hex2bin($data), 256);

        // 3. Sign with Elliptic
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKey);
        
        // Solidity expects the Ethereum signed message format: "\x19Ethereum Signed Message:\n32" + hash
        $prefix = "\x19Ethereum Signed Message:\n32";
        $finalHash = Keccak::hash($prefix . hex2bin($hash), 256);
        
        $signature = $key->sign($finalHash, ['canonical' => true]);
        
        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($signature->recoveryParam + 27);

        return '0x' . $r . $s . $v;
    }

    private function encodePacked(array $items): string
    {
        $result = '';
        foreach ($items as $item) {
            [$type, $value] = $item;
            if ($type === 'address') {
                $clean = str_starts_with($value, '0x') ? substr($value, 2) : $value;
                $result .= str_pad(strtolower($clean), 40, '0', STR_PAD_LEFT);
            } elseif ($type === 'uint256') {
                // Convert to hex and pad to 64 chars (32 bytes)
                // Using gmp for large numbers
                $hex = gmp_strval(gmp_init($value), 16);
                $result .= str_pad($hex, 64, '0', STR_PAD_LEFT);
            }
        }
        return $result;
    }
}
