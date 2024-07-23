<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Challenge;
use App\Models\User;
use Auth;
use App\Events\testevent;

class ChallengeController extends Controller
{
    public function sendChallenge(User $user)
    {
        if ($user->id == Auth::id()) {
            return redirect()->route('dashboard')->with('status', 'You cannot challenge yourself.');
        }

        

        $existingChallenge = Challenge::where('sender_id', Auth::id())
            ->where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingChallenge) {
            return redirect()->route('dashboard')->with('status', 'You already have a pending challenge with this user.');
        }

        $challenge = new Challenge();
        $challenge->sender_id = Auth::id();
        $challenge->receiver_id = $user->id;
        $challenge->status = 'pending';
        $challenge->save();

        event(new testevent(Auth::user(),$challenge));

       

        return redirect()->route('dashboard')->with('status', 'Challenge sent!');
    }

    public function acceptChallenge(Challenge $invitation)
    {
        if ($invitation->receiver_id !== Auth::id()) {
            return redirect()->route('dashboard')->with('status', 'You are not authorized to accept this challenge.');
        }

        $invitation->status = 'accepted';
        $invitation->save();

        event(new testevent(Auth::user(),$invitation));

        return redirect()->route('dashboard')->with('status', 'Challenge accepted!');
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
