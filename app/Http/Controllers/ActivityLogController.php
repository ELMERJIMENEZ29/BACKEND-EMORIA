<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\DiaryEntry;
use App\Models\EmotionalHistory;
use App\Models\EmotionLog;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type'             => 'required|in:breathing,music,diary,dass21',
            'duration_seconds' => 'integer|min:0',
        ]);

        $log = ActivityLog::create([
            'user_id'          => $request->user()->id,
            'type'             => $request->type,
            'duration_seconds' => $request->duration_seconds ?? 0,
        ]);

        return response()->json($log, 201);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $now    = Carbon::now();

        // Emociones en inglés (modelo) → score
        $emotionScores = [
            'happy'     => 95,
            'surprised' => 70,
            'neutral'   => 60,
            'disgusted' => 35,
            'sad'       => 25,
            'angry'     => 20,
            'fearful'   => 15,
            'fear'      => 15,
        ];

        // Promedio de emociones detectadas esta semana
        $recentEmotions = EmotionLog::where('user_id', $userId)
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->get();

        $emotionScore = null;
        if ($recentEmotions->count() > 0) {
            $total = $recentEmotions->sum(
                fn($e) => $emotionScores[strtolower($e->emotion)] ?? 50
            );
            $emotionScore = round($total / $recentEmotions->count());
        }

        // Último DASS-21
        $lastDass  = EmotionalHistory::where('user_id', $userId)
            ->whereNotNull('depression_score')
            ->whereNotNull('anxiety_score')
            ->whereNotNull('stress_score')
            ->orderBy('created_at', 'desc')
            ->first();

        $dassScore = null;
        if ($lastDass) {
            $totalScore = $lastDass->depression_score
                        + $lastDass->anxiety_score
                        + $lastDass->stress_score;
            $dassScore = max(0, round(100 - ($totalScore / 126 * 100)));
        }

        // Combinar DASS-21 (70%) + emociones (30%)
        $wellnessScore = null;
        if ($dassScore !== null && $emotionScore !== null) {
            $wellnessScore = round(($dassScore * 0.7) + ($emotionScore * 0.3));
        } elseif ($dassScore !== null) {
            $wellnessScore = $dassScore;
        } elseif ($emotionScore !== null) {
            $wellnessScore = $emotionScore;
        }

        // Racha
        $streak   = 0;
        $checkDay = $now->copy();
        while (true) {
            $hasActivity = ActivityLog::where('user_id', $userId)
                ->whereDate('created_at', $checkDay->toDateString())
                ->exists();
            if (!$hasActivity) break;
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
            $day      = $now->copy()->subDays($i);
            $dayLabel = $day->locale('es')->isoFormat('ddd');

            $dasOfDay = EmotionalHistory::where('user_id', $userId)
                ->whereDate('created_at', $day->toDateString())
                ->whereNotNull('depression_score')
                ->whereNotNull('anxiety_score')
                ->whereNotNull('stress_score')
                ->first();

            // Emociones del día
            $emotionsOfDay = EmotionLog::where('user_id', $userId)
                ->whereDate('created_at', $day->toDateString())
                ->get();

            $dayEmotionScore = null;
            if ($emotionsOfDay->count() > 0) {
                $total = $emotionsOfDay->sum(
                    fn($e) => $emotionScores[strtolower($e->emotion)] ?? 50
                );
                $dayEmotionScore = round($total / $emotionsOfDay->count());
            }

            $dayDassScore = null;
            if ($dasOfDay) {
                $dayDassScore = max(0, round(100 - (
                    ($dasOfDay->depression_score + $dasOfDay->anxiety_score + $dasOfDay->stress_score)
                    / 126 * 100
                )));
            }

            // Combina para el gráfico también
            $dayMood = null;
            if ($dayDassScore !== null && $dayEmotionScore !== null) {
                $dayMood = round(($dayDassScore * 0.7) + ($dayEmotionScore * 0.3));
            } elseif ($dayDassScore !== null) {
                $dayMood = $dayDassScore;
            } elseif ($dayEmotionScore !== null) {
                $dayMood = $dayEmotionScore;
            }

            $weeklyData[] = [
                'day'      => ucfirst($dayLabel),
                'mood'     => $dayMood,
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
            'streak'             => $streak,
            'sessions_month'     => $sessionsThisMonth,
            'diary_count'        => $diaryCount,
            'wellness_score'     => $wellnessScore,
            'emotion_score'      => $emotionScore,
            'weekly_evolution'   => $weeklyData,
            'activities_by_type' => $byType,
        ]);
    }
}
