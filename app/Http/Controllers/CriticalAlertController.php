<?php

namespace App\Http\Controllers;

use App\Models\CriticalAlert;
use Illuminate\Http\Request;

class CriticalAlertController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'severity' => 'nullable|in:warning,critical',
            'message' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $settings = $user->settings;

        if (!$settings || !$settings->critical_alerts_enabled) {
            return response()->json(['message' => 'Las alertas criticas no estan activadas'], 422);
        }

        if (!$settings->emergency_contact_phone) {
            return response()->json(['message' => 'Debes configurar un contacto de emergencia'], 422);
        }

        $alert = CriticalAlert::create([
            'user_id' => $user->id,
            'status' => 'created',
            'severity' => $validated['severity'] ?? 'critical',
            'message' => $validated['message'] ?? null,
            'contact_name' => $settings->emergency_contact_name,
            'contact_phone' => $settings->emergency_contact_phone,
            'contact_relationship' => $settings->emergency_contact_relationship,
            'triggered_at' => now(),
        ]);

        return response()->json($alert, 201);
    }

    public function index(Request $request)
    {
        $alerts = CriticalAlert::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($alerts);
    }
}
