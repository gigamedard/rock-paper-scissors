<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function index()
    {
        $cards = \App\Models\Card::all();
        return response()->json($cards);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'effect_type' => 'required|string',
            'effect_value' => 'required|numeric',
            'duration_type' => 'required|string',
            'duration_value' => 'required|integer',
            'price' => 'required|numeric',
            'currency' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (!isset($validated['currency'])) $validated['currency'] = 'SNT';

        $card = \App\Models\Card::create($validated);
        return response()->json(['message' => 'Card created', 'card' => $card]);
    }

    public function update(Request $request, $id)
    {
        $card = \App\Models\Card::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'effect_type' => 'string',
            'effect_value' => 'numeric',
            'duration_type' => 'string',
            'duration_value' => 'integer',
            'price' => 'numeric',
            'currency' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $card->update($validated);
        return response()->json(['message' => 'Card updated', 'card' => $card]);
    }

    public function destroy($id)
    {
        $card = \App\Models\Card::findOrFail($id);
        $card->delete();
        return response()->json(['message' => 'Card deleted']);
    }
}
