<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index()
    {
        // Ideally verify admin here, but middleware is better.
        // Fetch all settings grouped by 'group'
        $settings = \App\Models\GameSetting::all()->groupBy('group');
        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        // Expecting an array of settings: key => value
        $data = $request->except(['_token']);

        foreach ($data as $key => $value) {
            // Find the setting to get its type for correct casting if needed, 
            // but updateOrCreate in Model is easier if we trust the key exists.
            // For safety, only update existing settings or allow creating new ones if intended.
            // Here we assume keys exist or we just update values.
            
            // We need to look up the type to know if we need to encode JSON etc?
            // For simplicity, we just save what we get, or look up current setting.
            
            $setting = \App\Models\GameSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = $value;
                $setting->save();
            }
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
