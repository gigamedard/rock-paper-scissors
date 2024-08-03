<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Challenge;
use App\Models\User;

use Auth;
use App\Events\testevent;
use App\Events\ChallengeSent;
use App\Events\ReceivedInvitationEvent;
use App\Events\ChallengeAccepted;


class ChallengeController extends Controller
{
    public function sendChallenge($userId)
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

        if ($invitation->receiver_id !== Auth::id()) {
            return response()->json(['status' => 'You are not authorized to accept this challenge.']);
        }

        $invitation->status = 'accepted';
        $invitation->save();

        event(new ChallengeAccepted($invitation->sender_id,$invitationId));

        return response()->json(['status' => 'Challenge accepted!']);
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
}
