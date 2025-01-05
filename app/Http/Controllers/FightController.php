<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fight;
use Auth;
use App\Traits\HistoricalDataTrait;

use App\Events\verdictReadyEvent;

class FightController extends Controller
{
    use HistoricalDataTrait;
    
    public function played($fightId, $selectedMove, $isAutoplay = false)
    {   

        if(!$isAutoplay)
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
                User::where('id', $fight->user1_id)->update(['status' => 'in_fight']);
            } elseif ($fight->user2_id === Auth::id()) {
                $fight->user2_chosed = $selectedMove;
                $fight->status = $fight->user1_chosed === 'nothing' ? 'waiting_for_user1' : 'waiting_for_result';
                User::where('id', $fight->user2_id)->update(['status' => 'in_fight']);
            }

            $fight->save();

            // Check if both users have made their choices
            if ($fight->status === 'waiting_for_result') {
                $result = $this->determineResult($fight->user1_chosed, $fight->user2_chosed);
                $fight->result = $result;
                $fight->status = 'waiting_for_result';
                $fight->save();


                $user1Gain = $fight->user1Gain();
                $user1Verdict = $fight->user1Verdict();
                $user2Gain = $fight->user2Gain();
                $user2Verdict = $fight->user2Verdict();

                User::whereIn('id', [$fight->user1_id, $fight->user2_id])
                ->update(['status' => 'available']);

                event(new verdictReadyEvent($fight,$fight->user1_id,$user1Gain,$user1Verdict));
                event(new verdictReadyEvent($fight,$fight->user2_id,$user2Gain,$user2Verdict));            
                return response()->json(['status' => 'Fight completed!', 'result' => $fight->result]);
            }


            return response()->json(['status' => 'Move registered! Waiting for opponent.']);
        }
        else
        {
            $selectedMove = getNextMove(auth()->id());
        }
        
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

    public function storeFightResult()
    {
        // Example data
        $user1WalletAddress = '0x123...';
        $user1MoveIndex = 1;
        $user2WalletAddress = '0x456...';
        $user2MoveIndex = 2;
        $winnerIndex = 1;
        $prize = 0.5;

        // Save the historical data
        $historicalData = $this->createHistoricalData(
            $user1WalletAddress,
            $user1MoveIndex,
            $user2WalletAddress,
            $user2MoveIndex,
            $winnerIndex,
            $prize
        );

        return response()->json($historicalData, 201);
    }
}
 
