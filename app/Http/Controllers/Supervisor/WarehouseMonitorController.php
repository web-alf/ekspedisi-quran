<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\DailyPackingTask;
use App\Models\JenisQuran;
use App\Models\PackingNotification;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\Cache\StatusPengirimanCache;

class WarehouseMonitorController extends Controller
{
    protected $assignmentService;

    public function __construct(
        PackingAssignmentService $assignmentService
    ) {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Get data for AJAX requests
     */
    public function getData()
    {
        try {
            return response()->json([
                'warehouseUsers' => $this->getWarehouseUsers(),
                'dailyTasks' => $this->getDailyTasks(),
                'recentActivities' => $this->getRecentActivities(),
                'summary' => $this->getSummaryStats(),
                'stockInfo' => $this->getWarehouseStockInfo(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting warehouse monitor data: '.$e->getMessage());

            // Return empty data structure to prevent frontend crashes
            return response()->json([
                'warehouseUsers' => [],
                'dailyTasks' => [],
                'recentActivities' => [],
                'summary' => [
                    'total_active_tasks' => 0,
                    'total_packed_today' => 0,
                    'total_packed_this_month' => 0,
                    'total_boxes_today' => 0,
                    'average_per_user' => 0,
                    'total_carry_over' => 0,
                ],
                'stockInfo' => [
                    'details' => [],
                    'totals' => [],
                    'last_updated' => '',
                ],
                'error' => 'Data temporarily unavailable',
                'timestamp' => now()->toISOString(),
            ], 200); // Still return 200 to avoid triggering error handlers
        }
    }

    /**
     * Get performance data for specific month
     */
    public function getPerformanceData(Request $request)
    {
        try {
            $month = $request->get('month', date('Y-m'));

            $stats = $this->getMonthlyPerformanceStats($month);
            $userPerformance = $this->getUserPerformanceSummary($month);

            return response()->json([
                'stats' => $stats,
                'userPerformance' => $userPerformance,
                'month' => $month,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting performance data: '.$e->getMessage());

            return response()->json([
                'stats' => [],
                'userPerformance' => [],
                'month' => $request->get('month', date('Y-m')),
                'error' => 'Performance data temporarily unavailable',
                'timestamp' => now()->toISOString(),
            ], 200);
        }
    }

    /**
     * Warehouse monitoring dashboard
     */
    public function index()
    {
        $user = auth()->user();

        // Permission-based gate: siapa saja dengan izin monitor bisa masuk
        if (! $user->can('supervisor.warehouse.monitor')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Akses ditolak. Halaman ini khusus untuk pengguna yang memiliki izin monitor gudang.');
        }

        // Get today's tasks for all warehouse users
        $todayTasks = DailyPackingTask::whereDate('tanggal_tugas', today())
            ->with(['user', 'packingBoxes'])
            ->get();

        // Get warehouse users stats
        $warehouseUsers = User::permission('warehouse.dashboard')
            ->where('is_active', true)
            ->with(['dailyPackingTasks' => function ($query) {
                $query->whereDate('tanggal_tugas', today());
            }])
            ->get();

        // Get available pengiriman count for free-pick system
        $availablePengirimanCount = $this->assignmentService->getAvailablePengirimanCount();

        // Get warehouse stock info
        $stockInfo = $this->getWarehouseStockInfo();

        // Get current month performance data
        $currentMonth = date('Y-m');
        $performanceStats = $this->getMonthlyPerformanceStats($currentMonth);
        $userPerformance = $this->getUserPerformanceSummary($currentMonth);

        return Inertia::render('Supervisor/WarehouseMonitor', [
            'dailyTasks' => $todayTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'user_id' => $task->user_id,
                    'user' => [
                        'id' => $task->user->id,
                        'name' => $task->user->name,
                        'email' => $task->user->email,
                    ],
                    'target_quantity' => $task->total_target - ($task->sisa_kemarin ?? 0),
                    'carry_over_quantity' => $task->sisa_kemarin ?? 0,
                    'total_target' => $task->total_target,
                    'packed_quantity' => $task->total_selesai ?? 0,
                    'progress_percentage' => $task->progress_percentage,
                    'status' => $task->status,
                    'started_at' => $task->started_at?->format('H:i'),
                    'completed_at' => $task->completed_at?->format('H:i'),
                    'total_boxes' => $task->packingBoxes->count(),
                    'sealed_boxes' => $task->packingBoxes->where('status', 'sealed')->count(),
                ];
            }),
            'warehouseUsers' => $this->getWarehouseUsers(),
            'recentActivities' => $this->getRecentActivities(),
            'summary' => $this->getSummaryStats(),
            'availablePengirimanCount' => $availablePengirimanCount,
            'stockInfo' => $stockInfo,
            // Add performance data to initial load
            'performanceStats' => $performanceStats,
            'userPerformance' => $userPerformance,
        ]);
    }

    /**
     * Redistribute task items
     */
    public function redistributeTask(Request $request, DailyPackingTask $task)
    {
        $request->validate([
            'target_user_ids' => 'required|array',
            'target_user_ids.*' => 'exists:users,id',
        ]);

        try {
            $result = $this->assignmentService->redistributeTasks(
                $task,
                $request->target_user_ids
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal redistribute: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Performance report
     */
    public function performanceReport()
    {
        // This week tasks
        $thisWeekTasks = DailyPackingTask::whereBetween('tanggal_tugas', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])
            ->with('user')
            ->get();

        // Group by user
        $userPerformance = $thisWeekTasks->groupBy('user_id')->map(function ($tasks, $userId) {
            $user = $tasks->first()->user;
            $totalTarget = $tasks->sum('total_target');
            $totalAchieved = $tasks->sum('total_selesai');

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'tasks_count' => $tasks->count(),
                'completed_count' => $tasks->where('status', 'completed')->count(),
                'total_target' => $totalTarget,
                'total_achieved' => $totalAchieved,
                'achievement_rate' => $totalTarget > 0 ? round($totalAchieved / $totalTarget * 100, 2) : 0,
                'avg_completion_time' => $tasks->where('completed_at')->avg(function ($task) {
                    return $task->completed_at ? $task->completed_at->hour : null;
                }),
            ];
        })->values();

        return Inertia::render('Supervisor/PerformanceReport', [
            'userPerformance' => $userPerformance,
            'weekRange' => [
                'start' => now()->startOfWeek()->format('Y-m-d'),
                'end' => now()->endOfWeek()->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Export performance data to Excel
     */
    public function exportPerformanceData(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,excel',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Get tasks data for the date range
            $tasks = DailyPackingTask::whereBetween('tanggal_tugas', [$startDate, $endDate])
                ->with(['user', 'assignedBy'])
                ->orderBy('tanggal_tugas', 'desc')
                ->orderBy('user_id')
                ->get();

            // Group by user for summary
            $userSummary = $tasks->groupBy('user_id')->map(function ($userTasks, $userId) {
                $user = $userTasks->first()->user;
                $totalTarget = $userTasks->sum('total_target');
                $totalAchieved = $userTasks->sum('total_selesai');

                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_tasks' => $userTasks->count(),
                    'completed_tasks' => $userTasks->where('status', 'completed')->count(),
                    'total_target' => $totalTarget,
                    'total_achieved' => $totalAchieved,
                    'achievement_rate' => $totalTarget > 0 ? round($totalAchieved / $totalTarget * 100, 2) : 0,
                    'avg_daily_target' => round($totalTarget / $userTasks->count(), 2),
                    'avg_daily_achieved' => round($totalAchieved / $userTasks->count(), 2),
                ];
            });

            // Prepare detailed data
            $detailedData = $tasks->map(function ($task) {
                return [
                    'date' => $task->tanggal_tugas->format('Y-m-d'),
                    'user_name' => $task->user->name,
                    'user_email' => $task->user->email,
                    'target' => $task->total_target,
                    'achieved' => $task->total_selesai,
                    'carry_over' => $task->sisa_kemarin,
                    'progress_percentage' => $task->progress_percentage,
                    'status' => $task->status,
                    'started_at' => $task->started_at ? $task->started_at->format('H:i') : null,
                    'completed_at' => $task->completed_at ? $task->completed_at->format('H:i') : null,
                    'assigned_by' => $task->assignedBy ? $task->assignedBy->name : 'System',
                    'notes' => $task->notes,
                ];
            });

            if ($request->format === 'csv') {
                return $this->generateCSV($userSummary, $detailedData, $startDate, $endDate);
            } else {
                return $this->generateExcel($userSummary, $detailedData, $startDate, $endDate);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal export data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate CSV export
     */
    private function generateCSV($userSummary, $detailedData, $startDate, $endDate)
    {
        $filename = 'warehouse_performance_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($userSummary, $detailedData) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fwrite($file, "\xEF\xBB\xBF");

            // Summary section
            fputcsv($file, ['=== RINGKASAN PERFORMA ===']);
            fputcsv($file, []);
            fputcsv($file, ['Nama', 'Email', 'Total Task', 'Task Selesai', 'Total Target', 'Total Tercapai', 'Tingkat Pencapaian (%)', 'Rata-rata Target Harian', 'Rata-rata Tercapai Harian']);

            foreach ($userSummary as $summary) {
                fputcsv($file, [
                    $summary['name'],
                    $summary['email'],
                    $summary['total_tasks'],
                    $summary['completed_tasks'],
                    $summary['total_target'],
                    $summary['total_achieved'],
                    $summary['achievement_rate'],
                    $summary['avg_daily_target'],
                    $summary['avg_daily_achieved'],
                ]);
            }

            // Detailed section
            fputcsv($file, []);
            fputcsv($file, ['=== DATA DETAIL ===']);
            fputcsv($file, []);
            fputcsv($file, ['Tanggal', 'Nama User', 'Email', 'Target', 'Tercapai', 'Carry Over', 'Progress (%)', 'Status', 'Mulai', 'Selesai', 'Assigned By', 'Catatan']);

            foreach ($detailedData as $data) {
                fputcsv($file, [
                    $data['date'],
                    $data['user_name'],
                    $data['user_email'],
                    $data['target'],
                    $data['achieved'],
                    $data['carry_over'],
                    $data['progress_percentage'],
                    $data['status'],
                    $data['started_at'],
                    $data['completed_at'],
                    $data['assigned_by'],
                    $data['notes'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate Excel export using Laravel Excel
     */
    private function generateExcel($userSummary, $detailedData, $startDate, $endDate)
    {
        try {
            $filename = "warehouse_performance_{$startDate->format('Y-m-d')}_to_{$endDate->format('Y-m-d')}.xlsx";

            $exportData = [
                'user_summary' => $userSummary->values()->toArray(),
                'detailed_data' => $detailedData->toArray(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user()->name,
            ];

            return response()->streamDownload(function () use ($exportData) {
                $this->generateExcelFile($exportData);
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error('Excel export failed: '.$e->getMessage());

            // Fallback to CSV
            return $this->generateCSV($userSummary, $detailedData, $startDate, $endDate);
        }
    }

    /**
     * Generate Excel file content
     */
    private function generateExcelFile($data)
    {
        // Create a simple Excel-like format using XML
        echo "<?xml version=\"1.0\"?>\n";
        echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";
        echo "<Worksheet ss:Name=\"Performance Summary\">\n";
        echo "<Table>\n";

        // Summary header
        echo "<Row>\n";
        echo "<Cell><Data ss:Type=\"String\">User Name</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Email</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Total Tasks</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Completed Tasks</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Total Target</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Total Achieved</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Achievement Rate (%)</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Avg Daily Target</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Avg Daily Achieved</Data></Cell>\n";
        echo "</Row>\n";

        // Summary data
        foreach ($data['user_summary'] as $user) {
            echo "<Row>\n";
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($user['name'])."</Data></Cell>\n";
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($user['email'])."</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['total_tasks']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['completed_tasks']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['total_target']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['total_achieved']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['achievement_rate']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['avg_daily_target']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$user['avg_daily_achieved']}</Data></Cell>\n";
            echo "</Row>\n";
        }

        echo "</Table>\n";
        echo "</Worksheet>\n";

        // Detailed data worksheet
        echo "<Worksheet ss:Name=\"Detailed Data\">\n";
        echo "<Table>\n";

        // Detailed header
        echo "<Row>\n";
        echo "<Cell><Data ss:Type=\"String\">Date</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">User Name</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Email</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Target</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Achieved</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Status</Data></Cell>\n";
        echo "<Cell><Data ss:Type=\"String\">Assigned By</Data></Cell>\n";
        echo "</Row>\n";

        // Detailed data
        foreach ($data['detailed_data'] as $detail) {
            echo "<Row>\n";
            echo "<Cell><Data ss:Type=\"String\">{$detail['date']}</Data></Cell>\n";
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($detail['user_name'])."</Data></Cell>\n";
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($detail['user_email'])."</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$detail['target']}</Data></Cell>\n";
            echo "<Cell><Data ss:Type=\"Number\">{$detail['achieved']}</Data></Cell>\n";
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($detail['status'])."</Data></Cell>\n";
            echo '<Cell><Data ss:Type="String">'.htmlspecialchars($detail['assigned_by'])."</Data></Cell>\n";
            echo "</Row>\n";
        }

        echo "</Table>\n";
        echo "</Worksheet>\n";
        echo "</Workbook>\n";
    }

    /**
     * REMOVED: Old manual assignment page - replaced with target assignment
     * Use assignTargetToUser() instead
     */

    /**
     * Assign target-only to user (NEW SYSTEM)
     */
    public function assignTargetToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'target' => 'required|integer|min:1|max:200',
        ]);

        try {
            $user = User::find($request->user_id);

            // Allow update if task already exists
            $result = $this->assignmentService->assignDailyTaskToUserWithTarget(
                $user,
                today(),
                $request->target,
                true // Allow update
            );

            // Determine success message based on status
            $message = $result['status'] === 'target_updated'
                ? "Target berhasil diperbarui menjadi {$result['new_target']} mushaf untuk {$user->name}"
                : "Target {$request->target} mushaf berhasil di-assign ke {$user->name}";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menetapkan target: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * DEPRECATED: Old item assignment system - use assignBoxBasedTarget() instead
     * This method has been disabled in favor of box-based assignment
     */
    public function assignItems(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Method ini sudah tidak digunakan. Gunakan target assignment saja.',
        ], 410); // 410 Gone
    }

    /**
     * DEPRECATED: Unassign items feature - not needed in target-only system
     * In target-only system, users pick items freely by scanning QR codes
     */
    public function unassignItems(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Method ini sudah tidak digunakan dalam sistem target-only.',
        ], 410); // 410 Gone
    }

    /**
     * Delete a task (only if not started)
     */
    public function deleteTask(DailyPackingTask $task)
    {
        try {
            // Check if task can be deleted
            if ($task->total_selesai > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task sudah dimulai, tidak dapat dihapus',
                ], 400);
            }

            if ($task->status !== DailyPackingTask::STATUS_ASSIGNED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya task dengan status "assigned" yang dapat dihapus',
                ], 400);
            }

            DB::beginTransaction();

            // Delete notifications
            PackingNotification::where('daily_packing_task_id', $task->id)->delete();

            // Delete task items (this will free up the pengiriman)
            $task->taskItems()->delete();

            // Delete the task
            $taskUserId = $task->user_id;
            $task->delete();

            DB::commit();

            Log::info('Task deleted by supervisor', [
                'task_id' => $task->id,
                'user_id' => $taskUserId,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete task: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus task: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to get warehouse users
     */
    private function getWarehouseUsers()
    {
        return User::permission('warehouse.dashboard')
            ->where('is_active', true)
            ->with(['dailyPackingTasks' => function ($query) {
                $query->whereDate('tanggal_tugas', today());
            }])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });
    }

    /**
     * Helper method to get daily tasks
     */
    private function getDailyTasks()
    {
        return DailyPackingTask::whereDate('tanggal_tugas', today())
            ->with(['user', 'packingBoxes'])
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'user_id' => $task->user_id,
                    'user' => [
                        'id' => $task->user->id,
                        'name' => $task->user->name,
                        'email' => $task->user->email,
                    ],
                    'target_quantity' => $task->total_target - $task->sisa_kemarin,
                    'carry_over_quantity' => $task->sisa_kemarin ?? 0,
                    'total_target' => $task->total_target,
                    'packed_quantity' => $task->total_selesai ?? 0,
                    'progress_percentage' => $task->progress_percentage,
                    'status' => $task->status,
                    'started_at' => $task->started_at?->format('H:i'),
                    'completed_at' => $task->completed_at?->format('H:i'),
                    'total_boxes' => $task->packingBoxes->count(),
                    'sealed_boxes' => $task->packingBoxes->where('status', 'sealed')->count(),
                ];
            });
    }

    /**
     * Helper method to get recent activities
     */
    private function getRecentActivities()
    {
        // This is a placeholder - you may want to implement a proper activity log
        return PackingNotification::where('created_at', '>=', today())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($notification) {
                return [
                    'type' => $notification->type ?? 'general',
                    'description' => $notification->message,
                    'created_at' => $notification->created_at,
                ];
            });
    }

    /**
     * Helper method to get summary stats
     */
    private function getSummaryStats()
    {
        try {
            $todayTasks = DailyPackingTask::whereDate('tanggal_tugas', today())
                ->with('packingBoxes')
                ->get();

            // Get total boxes count properly
            $totalBoxes = 0;
            foreach ($todayTasks as $task) {
                $totalBoxes += $task->packingBoxes ? $task->packingBoxes->count() : 0;
            }

            $totalPackedToday = $todayTasks->sum('total_selesai') ?? 0;
            $totalPackedThisMonth = DailyPackingTask::whereMonth('tanggal_tugas', now()->month)
                ->whereYear('tanggal_tugas', now()->year)
                ->sum('total_selesai') ?? 0;
            $totalCarryOver = $todayTasks->sum('sisa_kemarin') ?? 0;
            $activeTasksCount = $todayTasks->whereIn('status', ['assigned', 'in_progress'])->count();
            $averagePerUser = $todayTasks->count() > 0 ? $totalPackedToday / $todayTasks->count() : 0;

            return [
                'total_active_tasks' => $activeTasksCount,
                'total_packed_today' => $totalPackedToday,
                'total_packed_this_month' => $totalPackedThisMonth,
                'total_boxes_today' => $totalBoxes,
                'average_per_user' => $averagePerUser,
                'total_carry_over' => $totalCarryOver,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting summary stats: '.$e->getMessage());

            return [
                'total_active_tasks' => 0,
                'total_packed_today' => 0,
                'total_packed_this_month' => 0,
                'total_boxes_today' => 0,
                'average_per_user' => 0,
                'total_carry_over' => 0,
            ];
        }
    }

    /**
     * Helper method to get monthly performance stats
     */
    private function getMonthlyPerformanceStats($month)
    {
        $monthStart = Carbon::parse($month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return User::permission('warehouse.dashboard')
            ->where('is_active', true)
            ->with(['dailyPackingTasks' => function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('tanggal_tugas', [$monthStart, $monthEnd])
                    ->with('packingBoxes');
            }])
            ->get()
            ->map(function ($user) {
                $tasks = $user->dailyPackingTasks;
                $totalPacked = $tasks->sum('total_selesai');
                $totalTarget = $tasks->sum('total_target');
                $totalBoxes = 0;
                foreach ($tasks as $task) {
                    $totalBoxes += $task->packingBoxes ? $task->packingBoxes->count() : 0;
                }

                $bestDay = $tasks->sortByDesc('total_selesai')->first();
                $achievementRate = $totalTarget > 0 ? ($totalPacked / $totalTarget) * 100 : 0;
                $avgPerDay = $tasks->count() > 0 ? $totalPacked / $tasks->count() : 0;

                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'total_packed' => $totalPacked,
                    'total_boxes' => $totalBoxes,
                    'avg_per_day' => round($avgPerDay, 2),
                    'best_day_quantity' => $bestDay ? $bestDay->total_selesai : 0,
                    'best_day_date' => $bestDay ? $bestDay->tanggal_tugas : null,
                    'achievement_rate' => round($achievementRate, 2),
                ];
            })
            ->filter(function ($stat) {
                return $stat['total_packed'] > 0; // Only show users with activity
            })
            ->values();
    }

    /**
     * Helper method to get user performance summary
     */
    private function getUserPerformanceSummary($month)
    {
        try {
            $monthStart = Carbon::parse($month.'-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $tasks = DailyPackingTask::whereBetween('tanggal_tugas', [$monthStart, $monthEnd])->get();

            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('status', 'completed')->count();
            $totalMushaf = $tasks->sum('total_selesai') ?? 0;
            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            $summary = [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'total_mushaf' => $totalMushaf,
                'completion_rate' => $completionRate,
            ];

            return [
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting user performance summary: '.$e->getMessage());

            return [
                'summary' => [
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'total_mushaf' => 0,
                    'completion_rate' => 0,
                ],
            ];
        }
    }

    /**
     * Get warehouse stock information
     */
    private function getWarehouseStockInfo()
    {
        // Get all jenis quran
        $jenisQurans = JenisQuran::active()->get();

        $stockInfo = [];

        foreach ($jenisQurans as $jenis) {
            // Stock calculation based on status:
            // Previous stock (can be enhanced with inventory table later)
            $previousStock = 0;

            // Get status IDs for calculation
            $statusIds = [
                'kedatangan' => StatusPengirimanCache::getIdBySlug('kedatangan'),
                'packing' => StatusPengirimanCache::getIdBySlug('packing'),
                'selesai-packing' => StatusPengirimanCache::getIdBySlug('selesai-packing'),
                'pengiriman' => StatusPengirimanCache::getIdBySlug('pengiriman'),
                'diterima' => StatusPengirimanCache::getIdBySlug('diterima'),
            ];

            // Incoming stock (status: kedatangan - ready to pack)
            $incoming = Pengiriman::where('jenis_quran_id', $jenis->id)
                ->where('status_id', $statusIds['kedatangan'])
                ->sum('jumlah_quran');

            // Siap Distribusi (hanya status selesai packing)
            $inProcess = Pengiriman::where('jenis_quran_id', $jenis->id)
                ->where('status_id', $statusIds['selesai-packing'])
                ->sum('jumlah_quran');

            // Distributed stock (status: diterima)
            $distributed = Pengiriman::where('jenis_quran_id', $jenis->id)
                ->where('status_id', $statusIds['diterima'])
                ->sum('jumlah_quran');

            // Total collected (all pengiriman for this jenis)
            $totalCollected = Pengiriman::where('jenis_quran_id', $jenis->id)
                ->whereNotNull('donatur_id')
                ->sum('jumlah_quran');

            // Current available stock = previous + incoming (ready to pack)
            $currentStock = $previousStock + $incoming;

            // Purchase recommendation (if stock is low - threshold can be adjusted)
            $threshold = 100; // Minimum stock threshold
            $recommendedPurchase = max(0, $threshold - $currentStock);

            $stockInfo[] = [
                'jenis_id' => $jenis->id,
                'jenis_name' => $jenis->nama_jenis,
                'jenis_code' => $jenis->kode_jenis,
                'previous_stock' => $previousStock,
                'incoming' => $incoming,
                'in_process' => $inProcess,
                'distributed' => $distributed,
                'current_stock' => $currentStock,
                'total_collected' => $totalCollected,
                'recommended_purchase' => $recommendedPurchase,
                'is_low_stock' => $currentStock < $threshold,
            ];
        }

        // Calculate totals
        $totals = [
            'total_stock' => collect($stockInfo)->sum('current_stock'),
            'total_incoming' => collect($stockInfo)->sum('incoming'),
            'total_in_process' => collect($stockInfo)->sum('in_process'),
            'total_distributed' => collect($stockInfo)->sum('distributed'),
            'total_collected' => collect($stockInfo)->sum('total_collected'),
            'total_recommended_purchase' => collect($stockInfo)->sum('recommended_purchase'),
        ];

        return [
            'details' => $stockInfo,
            'totals' => $totals,
            'last_updated' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
