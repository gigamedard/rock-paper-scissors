<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Challenge;
use App\Models\User;
use App\Models\Fight;

use Auth;
use App\Events\testevent;
use App\Events\ChallengeSent;
use App\Events\ReceivedInvitationEvent;
use App\Events\ChallengeAccepted;

use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;


class ChallengeController extends Controller
{
    public function sendChallenge($userId,$baseBetAmount/*, $maxBetAmount*/)
    {   

        $challengedUser = User::findOrFail($userId);
        $currentUser = Auth::user();
        
        if ($challengedUser->id == $currentUser->id) {
            
            return response()->json(['status' => 'You cannot challenge yourself.']);
        }

        
        $existingChallenge = Challenge::where('sender_id', $currentUser->id)
            ->where('receiver_id', $challengedUser->id)
            ->where('status', 'pending')
            ->first();

        if ($existingChallenge) {
            return response()->json(['status' => 'You already have a pending challenge with this user.']);
        }

        $challenge = new Challenge();
        $challenge->sender_id = $currentUser->id;
        $challenge->base_bet_amount = $baseBetAmount;
        //$challenge->max_bet_amount = $maxBetAmount;
        $challenge->receiver_id = $challengedUser->id;
        $challenge->status = 'pending';
        $challenge->save();
        

     
        event(new challengeSent($currentUser,$challenge, $challengedUser->name));
        event(new ReceivedInvitationEvent($challengedUser,$challenge,$currentUser->name));    
        //event(new testevent());
        return response()->json(['status' => 'ok','invitationId'=>$challenge->id,"challengerId" => $challenge->receiver_id]);

    }

    public function acceptChallenge($invitationId)
    {
        $invitation = Challenge::findOrFail($invitationId);
    
        // Check if the authenticated user is the receiver of the challenge
        if ($invitation->receiver_id !== Auth::id()) {
            return response()->json(['status' => 'You are not authorized to accept this challenge.']);
        }
    
        // Delete the challenge after acceptance

    
        // Create a new fight
        $fight = new Fight();
        $fight->user1_id = $invitation->sender_id;
        $fight->user2_id = $invitation->receiver_id;
        $fight->base_bet_amount = $invitation->base_bet_amount;
        //$fight->max_bet_amount = $invitation->max_bet_amount;
        $fight->status = 'waiting_for_both';  // Set the initial status of the fight
        $fight->save();
    
        // Trigger the ChallengeAccepted event with the fight ID
        event(new ChallengeAccepted($invitation->sender_id, $invitation->id,$fight->id, $fight->created_at->timestamp));
        $invitation->delete();
        // Return the fight ID in the response
        return response()->json(['status' => 'Challenge accepted!', 'fightId' => $fight->id, 'createdAt' => $fight->created_at->timestamp]);
    }
    

    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
        ]);

        $existingChallenge = Challenge::where('sender_id', auth()->id())
            ->where('receiver_id', $request->input('receiver_id'))
            ->where('status', 'pending')
            ->first();

        if ($existingChallenge) {
            return redirect()->back()->with('status', 'You already have a pending challenge with this user.');
        }

        Challenge::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->input('receiver_id'),
            'status' => 'pending',
        ]);

        return redirect()->back()->with('status', 'Challenge sent successfully.');
    }

    public function update(Request $request, Challenge $challenge)
    {
        if ($challenge->receiver_id !== auth()->id()) {
            return redirect()->back()->with('status', 'You are not authorized to update this challenge.');
        }

        $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        $challenge->update(['status' => $request->input('status')]);

        return redirect()->back()->with('status', 'Challenge status updated.');
    }

    public function deleteOldChallenges()
    {
        // Start a database transaction
        DB::beginTransaction();

        $totlal = 0;
    
        try {
            // Lock the rows that are selected for deletion using a FOR UPDATE lock
            $challenges = Challenge::where('created_at', '<=', now()->subSeconds(15))
                ->orderBy('created_at', 'asc')
                ->limit(50) // Limit to 50 challenges as per your initial requirement
                ->lockForUpdate() // This locks the selected rows for the duration of the transaction
                ->get();
    
            if ($challenges->count() > 0) {
                foreach ($challenges as $challenge) {
                    try {
                        // Attempt to delete each challenge
                        $challenge->delete();
                    } catch (Exception $e) {
                        // Handle any exceptions during the deletion of a challenge
                        Log::error("Failed to delete challenge ID {$challenge->id}: " . $e->getMessage());
                    }
                }

                $totlal = $challenges->count();
            }
    
            // Commit the transaction if all challenges were deleted successfully
            DB::commit();
        } catch (Exception $e) {
            // Rollback the transaction if something goes wrong and log the error
            DB::rollBack();
            Log::error('Failed to delete old challenges: ' . $e->getMessage());
        }
    
        return response()->json(['message' => ' '.$totlal.' Process completed. Check logs for any errors.']);
    }
    
}
