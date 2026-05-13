<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PreMoveService
{
    protected $userDataService;

    public function __construct(UserDataService $userDataService)
    {
        $this->userDataService = $userDataService;
    }

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
                'cid'           => $data['cid'],
            ]
        );

        // Register user for autoplay and (stub) store on blockchain.
        $this->userDataService->registerForAutoplay($data['user_id'], $bet_amount);
        
        // Clear previous payout signature as a new session is starting
        User::where('id', $data['user_id'])->update(['payout_signature' => null]);

        $this->storeOnBlockchain($hashedMoves);

        return [
            'message' => 'Pre-moves stored successfully!',
            'hash'    => hash('sha3-256', json_encode($hashedMoves)),
        ];
    }

    protected function storeOnBlockchain(array $hashedMoves)
    {
        // TODO: Implement blockchain storage logic or use BlockchainService.
    }

    public function unregisterFromAutoplay($user)
    {
        $this->userDataService->unregisterFromAutoplay($user);

        return ['message' => 'User unregistered from autoplay successfully!'];
    }
}
