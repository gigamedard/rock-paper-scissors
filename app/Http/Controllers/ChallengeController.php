<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Challenge;

class ChallengeController extends Controller
{
    public function store(Request $request)
    {
        Challenge::create([
            'challenger_id' => auth()->id(),
            'challengee_id' => $request->input('challengee_id'),
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Challenge $challenge)
    {
        if ($challenge->challengee_id === auth()->id()) {
            $challenge->update(['status' => $request->input('status')]);
        }

        return redirect()->back();
    }
}
