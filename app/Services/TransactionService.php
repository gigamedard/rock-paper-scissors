namespace App\Services;

use App\Models\Transaction;
use App\Models\Game;

class TransactionService
{
    public function handleTransaction(Game $game)
    {
        if ($game->winner == 'draw') {
            return;
        }

        $winner = $game->winner == 'user1' ? $game->user1 : $game->user2;
        $loser = $game->winner == 'user1' ? $game->user2 : $game->user1;

        Transaction::create([
            'user_id' => $winner->id,
            'game_id' => $game->id,
            'amount' => $game->bet_amount,
        ]);

        Transaction::create([
            'user_id' => $loser->id,
            'game_id' => $game->id,
            'amount' => -$game->bet_amount,
        ]);
    }
}
