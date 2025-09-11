<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index()
    {
        // Show the flag selection screen
        return view('language-selection');
    }

    public function setLanguage($locale)
    {   
        session(['locale' => $locale]);

        // If user is logged in, persist in DB too
        if (auth()->check()) {
            auth()->user()->update(['language' => $locale]);
        }

        return redirect('/'); // send them to the game start
    }
}
