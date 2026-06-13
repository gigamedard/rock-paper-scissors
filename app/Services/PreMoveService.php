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
        $user = User::findOrFail($data['user_id']);
        
        if ($user->payout_signature) {
            abort(422, 'Veuillez réclamer vos gains de la session précédente avant de démarrer.');
        }

        if ($user->cooldown_until && $user->cooldown_until->isFuture()) {
            abort(422, 'Le cooldown est encore actif. Vous ne pouvez pas rejoindre la partie pour le moment.');
        }

        $limits = $user->getActiveLimits();

        $bet_amount = $data['bet_amount'];
        $target_q = $data['target_q'] ?? 2.0;
        $cooldown_time = $data['cooldown_time'] ?? 1440;

        // Perform validations against limits
        if ($bet_amount > $limits['max_base_bet']) {
            abort(422, 'Bet amount exceeds the authorized limit.');
        }

        if ($target_q > $limits['max_q']) {
            abort(422, 'Target multiplier Q exceeds the authorized limit.');
        }

        if ($cooldown_time < $limits['min_cooldown']) {
            abort(422, 'Cooldown time is less than the authorized minimum limit.');
        }

        $nonce = bin2hex(random_bytes(16));
        $preMoves = $data['pre_moves'];

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
        $this->userDataService->registerForAutoplay($data['user_id'], $bet_amount, $target_q, $cooldown_time);
        
        // Clear previous payout signature as a new session is starting
        $user->update(['payout_signature' => null]);

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
