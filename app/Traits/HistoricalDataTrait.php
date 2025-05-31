<?php

namespace App\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

trait HistoricalDataTrait
{
    /**
     * Create a historical data record.
     *
     * @param string $user1WalletAddress
     * @param int $user1MoveIndex
     * @param string $user2WalletAddress
     * @param int $user2MoveIndex
     * @param int $winnerIndex
     * @param float $prize
     * @return \App\Models\HistoricalData
     */
    public function createHistoricalData(
        string $user1WalletAddress,
        int $user1MoveIndex,
        string $user2WalletAddress,
        int $user2MoveIndex,
        int $winnerIndex,
        float $prize
    ) {
        $data = [
            'user1_wallet_address' => $user1WalletAddress,
            'user1_move_index' => $user1MoveIndex,
            'user2_wallet_address' => $user2WalletAddress,
            'user2_move_index' => $user2MoveIndex,
            'winner_index' => $winnerIndex,
            'prize' => $prize,
        ];

        // Hash the data
        $data['encryption'] = $this->generateHash($data);

        // Save the historical data
        return \App\Models\HistoricalData::create($data);
    }

    /**
     * Generate a hash for the given data.
     *
     * @param array $data
     * @return string
     */
    public function generateEncryptedData(array $data): string
    {
        // Encrypt data
        return $encryptedData = Crypt::encrypt($data);
    }

    public function recoverFromEncryptedData(string $encryptedData): string
    {
        // Decrypt data
        return Crypt::decrypt($encryptedData);;
    }

}
