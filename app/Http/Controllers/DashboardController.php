<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $onlineUsers = User::where('is_online', true)->where('id', '!=', auth()->id())->get();
        $receivedInvitations = auth()->user()->challengesReceived()->where('status', 'pending')->get();
        $sentInvitations = auth()->user()->challengesSent()->where('status', 'pending')->get();
        $currentUser = Auth::user();
        $currentUser->is_online = true;


        return view('welcome', compact('onlineUsers', 'receivedInvitations', 'sentInvitations','currentUser'));
    }
}
