<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmotionalHistory;

class EmotionalHistoryController extends Controller
{
    public function index(Request $request)
    {
        $entries = EmotionalHistory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entries);
    }

    public function store(Request $request)
    {
        $request->validate([
            'companion'           => 'required|string',
            'recognized_emotion'  => 'nullable|string',
            'depression_score'    => 'required|integer',
            'anxiety_score'       => 'required|integer',
            'stress_score'        => 'required|integer',
            'depression_severity' => 'required|string',
            'anxiety_severity'    => 'required|string',
            'stress_severity'     => 'required|string',
        ]);

        $entry = EmotionalHistory::create([
            'user_id'             => $request->user()->id,
            'companion'           => $request->companion,
            'recognized_emotion'  => $request->recognized_emotion,
            'depression_score'    => $request->depression_score,
            'anxiety_score'       => $request->anxiety_score,
            'stress_score'        => $request->stress_score,
            'depression_severity' => $request->depression_severity,
            'anxiety_severity'    => $request->anxiety_severity,
            'stress_severity'     => $request->stress_severity,
        ]);

        return response()->json($entry, 201);
    }

    public function destroy(Request $request, EmotionalHistory $emotionalHistory)
    {
        if ($emotionalHistory->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $emotionalHistory->delete();

        return response()->json(['message' => 'Entrada eliminada']);
    }
}
