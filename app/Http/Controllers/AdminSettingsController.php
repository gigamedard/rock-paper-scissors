<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(Request $request)
    {
        // Fetch all settings grouped by 'group'
        $settings = \App\Models\GameSetting::all()->groupBy('group');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        // Expecting an array of settings: key => value
        $data = $request->json()->all();

        foreach ($data as $key => $value) {
            $setting = \App\Models\GameSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = $value;
                $setting->save();
            }
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}
