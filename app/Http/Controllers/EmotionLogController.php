<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmotionalHistory;
use App\Models\EmotionLog;

class EmotionLogController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'emotion' => 'required|string',
        ]);

        $log = EmotionLog::create([
            'user_id' => $request->user()->id,
            'emotion' => strtolower($request->emotion),
        ]);

        $history = EmotionalHistory::create([
            'user_id' => $request->user()->id,
            'companion' => 'emotion-model',
            'recognized_emotion' => $log->emotion,
        ]);

        return response()->json([
            'emotion_log' => $log,
            'emotional_history' => $history,
        ], 201);
    }
}
