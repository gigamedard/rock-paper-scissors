<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game; // Assuming you have a Game model
use Auth;

class GameController extends Controller
{
    public function index()
    {
        return view('game.index');
    }

    public function play(Request $request)
    {
        $validated = $request->validate([
            'move' => 'required|string|in:rock,paper,scissors',
            'opponent_id' => 'required|exists:users,id',
        ]);

        // Implement your game logic here
        $userMove = $validated['move'];
        $opponentMove = ['rock', 'paper', 'scissors'][array_rand(['rock', 'paper', 'scissors'])];

        if ($userMove === $opponentMove) {
            $result = 'It\'s a tie!';
        } elseif (
            ($userMove === 'rock' && $opponentMove === 'scissors') ||
            ($userMove === 'paper' && $opponentMove === 'rock') ||
            ($userMove === 'scissors' && $opponentMove === 'paper')
        ) {
            $result = 'You win!';
        } else {
            $result = 'You lose!';
        }

        return response()->json([
            'user_move' => $userMove,
            'opponent_move' => $opponentMove,
            'result' => $result,
        ]);
    }
}


