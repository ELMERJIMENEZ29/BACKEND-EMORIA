<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($this->settingsFor($request));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'preferences' => 'nullable|array',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:40',
            'emergency_contact_relationship' => 'nullable|string|max:80',
            'critical_alerts_enabled' => 'nullable|boolean',
        ]);

        $settings = UserSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json($settings);
    }

    private function settingsFor(Request $request): UserSetting
    {
        return UserSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'preferences' => [
                    'notifications' => true,
                    'sound' => true,
                    'theme' => 'system',
                    'language' => 'es',
                ],
                'critical_alerts_enabled' => false,
            ]
        );
    }
}
