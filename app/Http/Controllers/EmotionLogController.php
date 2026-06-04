<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        return response()->json($log, 201);
    }
}
