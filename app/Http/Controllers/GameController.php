<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index()
    {
        // Display the game interface
        return view('game');
    }

    public function play(Request $request)
    {
        // Game logic for Rock-Paper-Scissors
        $playerMove = $request->input('move');
        $computerMove = $this->generateComputerMove();
        $result = $this->determineWinner($playerMove, $computerMove);

        return view('result', compact('playerMove', 'computerMove', 'result'));
    }

    private function generateComputerMove()
    {
        $moves = ['rock', 'paper', 'scissors'];
        return $moves[array_rand($moves)];
    }

    private function determineWinner($playerMove, $computerMove)
    {
        // Rock-Paper-Scissors logic
        if ($playerMove === $computerMove) {
            return 'draw';
        }

        $winningMoves = [
            'rock' => 'scissors',
            'paper' => 'rock',
            'scissors' => 'paper',
        ];

        if ($winningMoves[$playerMove] === $computerMove) {
            return 'win';
        }

        return 'lose';
    }
}
