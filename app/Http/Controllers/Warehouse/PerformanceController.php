<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\DailyPackingTask;
use App\Models\UserPerformance;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PerformanceController extends Controller
{
    /**
     * Display performance page
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get current month performance
        $currentMonth = now()->format('Y-m');
        $performance = UserPerformance::where('user_id', $user->id)
            ->where('bulan', $currentMonth)
            ->first();

        // Get recent tasks
        $recentTasks = DailyPackingTask::where('user_id', $user->id)
            ->with(['packingBoxes'])
            ->orderBy('tanggal_tugas', 'desc')
            ->limit(30)
            ->get();

        // Calculate weekly performance
        $thisWeek = $recentTasks->filter(function($task) {
            return $task->tanggal_tugas->isCurrentWeek();
        });

        $lastWeek = $recentTasks->filter(function($task) {
            return $task->tanggal_tugas->isLastWeek();
        });

        return Inertia::render('Warehouse/Performance', [
            'performance' => $performance ? [
                'bulan' => $performance->bulan,
                'total_target' => $performance->total_target,
                'total_achieved' => $performance->total_achieved,
                'achievement_rate' => $performance->achievement_rate,
                'total_hari_kerja' => $performance->total_hari_kerja,
                'total_hari_complete' => $performance->total_hari_complete,
                'performance_level' => $performance->performance_level,
                'performance_badge' => $performance->performance_badge,
            ] : null,
            'weeklyComparison' => [
                'this_week' => [
                    'tasks' => $thisWeek->count(),
                    'completed' => $thisWeek->where('is_completed', true)->count(),
                    'total_target' => $thisWeek->sum('total_target'),
                    'total_achieved' => $thisWeek->sum('total_selesai'),
                ],
                'last_week' => [
                    'tasks' => $lastWeek->count(),
                    'completed' => $lastWeek->where('is_completed', true)->count(),
                    'total_target' => $lastWeek->sum('total_target'),
                    'total_achieved' => $lastWeek->sum('total_selesai'),
                ]
            ],
            'recentTasks' => $recentTasks->map(function($task) {
                return [
                    'id' => $task->id,
                    'tanggal_tugas' => $task->tanggal_tugas->format('Y-m-d'),
                    'total_target' => $task->total_target,
                    'total_selesai' => $task->total_selesai,
                    'progress_percentage' => $task->progress_percentage,
                    'status' => $task->status,
                    'is_completed' => $task->is_completed,
                    'boxes_count' => $task->packingBoxes->count(),
                    'started_at' => $task->started_at?->format('H:i'),
                    'completed_at' => $task->completed_at?->format('H:i'),
                ];
            })
        ]);
    }
}