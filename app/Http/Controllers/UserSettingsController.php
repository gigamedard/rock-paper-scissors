<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserSetting;
use Auth;

class UserSettingsController extends Controller
{
    public function getUserSettings()
    {
        $user = Auth::user();
    
        // Retrieve or create default settings
        $settings = $user->userSetting()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'base_bet_amount' => 8,  // Default value for base_bet_amount
                'same_bet_match' => true, // Default value for same_bet_match
                'max_bet_amount' => null  // Default value for max_bet_amount
            ]
        );
    
        // Return user settings as JSON
        return response()->json($settings);
    }

    public function saveUserSettings(Request $request)
    {
        $user = Auth::user();
    
        // Validate the request
        $request->validate([
            'base_bet_amount' => 'required|numeric|min:0',
            'same_bet_match' => 'required|boolean',
            'max_bet_amount' => 'nullable|numeric|min:0',
        ]);
    
        // Debugging: Dump the max_bet_amount from the request
        // dd($request->max_bet_amount);
    
        // Update or create user settings
        $user->userSetting()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'base_bet_amount' => $request->base_bet_amount,
                'same_bet_match' => $request->same_bet_match,
                'max_bet_amount' => $request->max_bet_amount
            ]
        );
    
        // Refresh the relationship to get the latest data
        $user->load('userSetting');
        $set = $user->userSetting;  // Access the updated related model
    
        // Debugging: Dump the max_bet_amount property
        //dd($set->max_bet_amount);
    
        return response()->json(['status' => 'Settings saved successfully.']);
    }
    
}

