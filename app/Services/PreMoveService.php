<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PreMoveService
{
    /**
     * Store pre-moves: hash moves with a nonce, update DB, and register user.
     */
    public function storePreMoves(array $data): array
    {
        $nonce = bin2hex(random_bytes(16));
        $preMoves = $data['pre_moves'];
        $bet_amount = $data['bet_amount'];

        $hashedMoves = array_map(fn($move) => hash('sha3-256', $move . $nonce), $preMoves);

        DB::table('pre_moves')->updateOrInsert(
            ['user_id' => $data['user_id']],
            [
                'moves'         => json_encode($preMoves),
                'hashed_moves'  => json_encode($hashedMoves),
                'nonce'         => $nonce,
                'current_index' => 0,
                'session_first_pool_id'=>0,
            ]
        );

        // Register user for autoplay and (stub) store on blockchain.
        $this->registerForAutoplay($data['user_id'], $bet_amount);
        $this->storeOnBlockchain($hashedMoves);

        return [
            'message' => 'Pre-moves stored successfully!',
            'hash'    => hash('sha3-256', json_encode($hashedMoves)),
        ];
    }

    protected function registerForAutoplay(int $userId, $bet_amount)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }
        $user->update([
            'autoplay_active' => true,
            'bet_amount'      => $bet_amount,
            'status'          => 'available',
        ]);
    }

    protected function storeOnBlockchain(array $hashedMoves)
    {
        // TODO: Implement blockchain storage logic or use BlockchainService.
    }

    public function unregisterFromAutoplay($user)
    {
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        $user->update([
            'autoplay_active' => false,
            'status'          => 'available',
        ]);

        return ['message' => 'User unregistered from autoplay successfully!'];
    }
}
