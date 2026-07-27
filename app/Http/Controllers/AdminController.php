<?php

namespace App\Http\Controllers;

use App\Models\EmotionalHistory;
use App\Models\EmotionLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $dassQuery = EmotionalHistory::query()->whereNotNull('depression_score');
        $facialQuery = EmotionLog::query();

        $severityDistribution = EmotionalHistory::query()
            ->whereNotNull('depression_score')
            ->get(['depression_severity', 'anxiety_severity', 'stress_severity'])
            ->flatMap(fn (EmotionalHistory $entry) => [
                $entry->depression_severity,
                $entry->anxiety_severity,
                $entry->stress_severity,
            ])
            ->filter()
            ->countBy();
        $activeUserIds = EmotionalHistory::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('user_id')
            ->merge(
                EmotionLog::query()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->pluck('user_id')
            )
            ->unique();

        return response()->json([
            'users' => User::query()->where('role', 'USUARIO')->count(),
            'active_users_30_days' => $activeUserIds->count(),
            'dass_assessments' => (clone $dassQuery)->count(),
            'facial_signals' => (clone $facialQuery)->count(),
            'users_with_dass' => (clone $dassQuery)->distinct('user_id')->count('user_id'),
            'severity_distribution' => $severityDistribution,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $search = trim($validated['search'] ?? '');

        $users = User::query()
            ->where('role', 'USUARIO')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->withCount([
                'emotionalHistories as dass_count' => fn ($query) => $query->whereNotNull('depression_score'),
                'emotionLogs as facial_count',
            ])
            ->withMax('emotionalHistories as last_emotional_record_at', 'created_at')
            ->withMax('emotionLogs as last_facial_record_at', 'created_at')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->through(function (User $user) {
                $lastRecords = array_filter([
                    $user->last_emotional_record_at,
                    $user->last_facial_record_at,
                ]);

                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
                    'created_at' => $user->created_at,
                    'dass_count' => $user->dass_count,
                    'facial_count' => $user->facial_count,
                    'last_emotional_record_at' => $lastRecords ? max($lastRecords) : null,
                ];
            });

        return response()->json($users);
    }

    public function userEvolution(User $user): JsonResponse
    {
        abort_if($user->role !== 'USUARIO', 404);

        $dass = $user->emotionalHistories()
            ->whereNotNull('depression_score')
            ->orderBy('created_at')
            ->get([
                'created_at',
                'depression_score',
                'anxiety_score',
                'stress_score',
                'depression_severity',
                'anxiety_severity',
                'stress_severity',
            ]);
        $facialQuery = $user->emotionLogs();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'created_at' => $user->created_at,
            ],
            'summary' => [
                'dass_count' => $dass->count(),
                'facial_count' => (clone $facialQuery)->count(),
                'first_dass_at' => $dass->first()?->created_at,
                'latest_dass_at' => $dass->last()?->created_at,
                'latest_dass' => $dass->last(),
            ],
            'dass_evolution' => $dass,
            'facial_summary' => [
                'predominant' => (clone $facialQuery)
                    ->select('emotion', DB::raw('COUNT(*) as total'))
                    ->groupBy('emotion')
                    ->orderByDesc('total')
                    ->first(),
                'recent' => (clone $facialQuery)
                    ->latest()
                    ->limit(10)
                    ->get(['emotion', 'created_at']),
            ],
        ]);
    }
}
