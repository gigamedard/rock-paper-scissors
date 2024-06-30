amespace App\Services;

use App\Models\Game;
use App\Models\User;

class GameService
{
    public function createGame(User $user1, User $user2 = null)
    {
        return Game::create([
            'user1_id' => $user1->id,
            'user2_id' => $user2 ? $user2->id : null,
            'bet_amount' => 10.00 // Example bet amount, can be dynamic
        ]);
    }

    public function makeChoice(Game $game, User $user, $choice)
    {
        if ($user->id == $game->user1_id) {
            $game->user1_choice = $choice;
        } elseif ($user->id == $game->user2_id) {
            $game->user2_choice = $choice;
        }

        if ($game->user1_choice && $game->user2_choice) {
            $this->determineWinner($game);
        }

        $game->save();
    }

    protected function determineWinner(Game $game)
    {
        $choices = ['rock' => 'scissors', 'scissors' => 'paper', 'paper' => 'rock'];
        if ($game->user1_choice == $game->user2_choice) {
            $game->winner = 'draw';
        } elseif ($choices[$game->user1_choice] == $game->user2_choice) {
            $game->winner = 'user1';
        } else {
            $game->winner = 'user2';
        }
    }
}
