<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DiaryEntry;
use App\Models\EmotionalHistory;
use App\Models\EmotionLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:breathing,music,diary,dass21',
            'duration_seconds' => 'integer|min:0',
        ]);

        $log = ActivityLog::create([
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'duration_seconds' => $request->duration_seconds ?? 0,
        ]);

        return response()->json($log, 201);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $now = Carbon::now();

        // Las expresiones faciales se conservan como señales categóricas.
        // No se transforman en una puntuación de salud o bienestar.
        $recentEmotion = EmotionLog::where('user_id', $userId)
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->latest()
            ->value('emotion');

        // Último DASS-21
        $lastDass = EmotionalHistory::where('user_id', $userId)
            ->whereNotNull('depression_score')
            ->whereNotNull('anxiety_score')
            ->whereNotNull('stress_score')
            ->orderBy('created_at', 'desc')
            ->first();

        // Racha
        $streak = 0;
        $checkDay = $now->copy();
        while (true) {
            $hasActivity = ActivityLog::where('user_id', $userId)
                ->whereDate('created_at', $checkDay->toDateString())
                ->exists();
            if (! $hasActivity) {
                break;
            }
            $streak++;
            $checkDay->subDay();
        }

        // Sesiones del mes
        $sessionsThisMonth = ActivityLog::where('user_id', $userId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        // Entradas del diario
        $diaryCount = DiaryEntry::where('user_id', $userId)->count();

        // Evolución últimos 7 días
        $weeklyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $dayLabel = $day->locale('es')->isoFormat('ddd');

            $dasOfDay = EmotionalHistory::where('user_id', $userId)
                ->whereDate('created_at', $day->toDateString())
                ->whereNotNull('depression_score')
                ->whereNotNull('anxiety_score')
                ->whereNotNull('stress_score')
                ->first();

            $weeklyData[] = [
                'day' => ucfirst($dayLabel),
                'depression_score' => $dasOfDay?->depression_score,
                'anxiety_score' => $dasOfDay?->anxiety_score,
                'stress_score' => $dasOfDay?->stress_score,
                'sessions' => ActivityLog::where('user_id', $userId)
                    ->whereDate('created_at', $day->toDateString())
                    ->count(),
            ];
        }

        // Actividades por tipo este mes
        $byType = ActivityLog::where('user_id', $userId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return response()->json([
            'streak' => $streak,
            'sessions_month' => $sessionsThisMonth,
            'diary_count' => $diaryCount,
            'latest_dass' => $lastDass ? [
                'depression_score' => $lastDass->depression_score,
                'anxiety_score' => $lastDass->anxiety_score,
                'stress_score' => $lastDass->stress_score,
            ] : null,
            'latest_visual_signal' => $recentEmotion,
            'weekly_evolution' => $weeklyData,
            'activities_by_type' => $byType,
        ]);
    }
}
