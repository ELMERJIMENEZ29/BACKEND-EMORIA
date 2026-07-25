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
        $weekStart = $now->copy()->subDays(6)->startOfDay();
        $weekEnd = $now->copy()->endOfDay();

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

        // Racha: una sola consulta para obtener los días con actividad.
        $activeDates = ActivityLog::where('user_id', $userId)
            ->selectRaw('DATE(created_at) as activity_date')
            ->distinct()
            ->orderByDesc('activity_date')
            ->pluck('activity_date')
            ->mapWithKeys(static fn ($date) => [(string) $date => true]);

        $streak = 0;
        $checkDay = $now->copy();
        while ($activeDates->has($checkDay->toDateString())) {
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

        // La evolución semanal se resuelve con dos consultas agrupadas, no
        // con dos consultas nuevas por cada día.
        $weeklyDass = EmotionalHistory::where('user_id', $userId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->whereNotNull('depression_score')
            ->whereNotNull('anxiety_score')
            ->whereNotNull('stress_score')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(static fn (EmotionalHistory $entry) => $entry->created_at->toDateString())
            ->map(static fn ($entries) => $entries->first());

        $weeklyActivityCounts = ActivityLog::where('user_id', $userId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date');

        $weeklyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $dayLabel = $day->locale('es')->isoFormat('ddd');
            $dateKey = $day->toDateString();
            $dasOfDay = $weeklyDass->get($dateKey);

            $weeklyData[] = [
                'day' => ucfirst($dayLabel),
                'depression_score' => $dasOfDay?->depression_score,
                'anxiety_score' => $dasOfDay?->anxiety_score,
                'stress_score' => $dasOfDay?->stress_score,
                'sessions' => (int) ($weeklyActivityCounts->get($dateKey) ?? 0),
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
