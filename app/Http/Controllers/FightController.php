<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fight;
use Auth;

class FightController extends Controller
{
    public function played($fightId, $selectedMove)
    {
        // Validate the move
        $validMoves = ['rock', 'paper', 'scissors'];
        if (!in_array($selectedMove, $validMoves)) {
            return response()->json(['status' => 'Invalid move.'], 400);
        }

        // Find the fight by ID
        $fight = Fight::findOrFail($fightId);

        // Check if the authenticated user is part of this fight
        if ($fight->user1_id !== Auth::id() && $fight->user2_id !== Auth::id()) {
            return response()->json(['status' => 'Unauthorized.'], 403);
        }

        // Update the user's choice
        if ($fight->user1_id === Auth::id()) {
            $fight->user1_chosed = $selectedMove;
            $fight->status = $fight->user2_chosed === 'nothing' ? 'waiting_for_user2' : 'waiting_for_result';
        } elseif ($fight->user2_id === Auth::id()) {
            $fight->user2_chosed = $selectedMove;
            $fight->status = $fight->user1_chosed === 'nothing' ? 'waiting_for_user1' : 'waiting_for_result';
        }

        $fight->save();

        // Check if both users have made their choices
        if ($fight->status === 'waiting_for_result') {
            $result = $this->determineResult($fight->user1_chosed, $fight->user2_chosed);
            $fight->result = $result;
            $fight->status = 'waiting_for_result';
            $fight->save();

            return response()->json(['status' => 'Fight completed!', 'result' => $fight->result]);
        }

        return response()->json(['status' => 'Move registered! Waiting for opponent.']);
    }

    private function determineResult($user1Move, $user2Move)
    {
        if ($user1Move === $user2Move) {
            return 'draw';
        }

        $winningCombinations = [
            'rock' => 'scissors',
            'scissors' => 'paper',
            'paper' => 'rock',
        ];

        return $winningCombinations[$user1Move] === $user2Move ? 'user1_win' : 'user2_win';
    }
}
