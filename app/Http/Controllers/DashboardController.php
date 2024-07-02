<?php
    namespace App\Http\Controllers;
    use Illuminate\Http\Request;
    use App\Models\User;
    use App\Models\Challenge;
    
    class DashboardController extends Controller
    {
        public function index()
        {
            $onlineUsers = User::where('is_online', true)->where('id', '!=', auth()->id())->get();
            $receivedInvitations = auth()->user()->challengesReceived()->where('status', 'pending')->get();
            $sentInvitations = auth()->user()->challengesSent()->where('status', 'pending')->get();

            return view('dashboard', compact('onlineUsers', 'receivedInvitations', 'sentInvitations'));
        }
    }
