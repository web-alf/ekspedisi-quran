<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\PackingNotification;
use App\Services\PackingAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $assignmentService;

    public function __construct(PackingAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Display warehouse dashboard
     */
    public function index()
    {
        try {
            $user = auth()->user();

            // Check if user has permission to access warehouse dashboard
            if (!$user->can('warehouse.dashboard')) {
                return redirect()->route('admin.dashboard')
                    ->with('error', 'Akses ditolak. Anda tidak memiliki permission untuk mengakses halaman ini.');
            }

            // Get or create today's task
            $todayTask = $this->getTodayTask($user);

            // Get notifications
            $notifications = PackingNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Get performance data
            $performanceData = $this->getPerformanceData($user);

            // Get active box if task is in progress
            $activeBox = null;
            if ($todayTask && $todayTask->is_active) {
                $activeBox = $todayTask->getCurrentBox();
            }

            // OPTIMIZED: Get recent boxes for this user with constrained eager loading
            $recentBoxes = PackingBox::whereHas('dailyPackingTask', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
                ->with([
                    'jenisQuran:id,nama_jenis',
                    'dailyPackingTask:id,user_id,tanggal_tugas',
                ])
                ->select('id', 'kode_kerdus', 'status', 'jenis_quran_id', 'jumlah_terisi', 'kapasitas', 'sealed_at', 'created_at', 'daily_packing_task_id')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($box) {
                    return [
                        'id' => $box->id,
                        'kode_kerdus' => $box->kode_kerdus,
                        'status' => $box->status,
                        'status_info' => $box->getStatusInfo(),
                        'jenis_quran' => $box->jenisQuran ? $box->jenisQuran->nama_jenis : 'Belum ditentukan',
                        'jumlah_terisi' => $box->jumlah_terisi,
                        'kapasitas' => $box->kapasitas,
                        'progress_percentage' => $box->progress_percentage,
                        'sealed_at' => $box->sealed_at?->format('d/m/Y H:i'),
                        'created_at' => $box->created_at->format('d/m/Y H:i'),
                    ];
                });

            // Get shared boxes for collaboration interface
            $sharedBoxes = $this->getSharedBoxesForCollaboration($user);

            return Inertia::render('Warehouse/Dashboard', [
                'todayTask' => $todayTask ? array_merge([
                    'id' => $todayTask->id,
                    'tanggal_tugas' => $todayTask->tanggal_tugas->format('Y-m-d'),
                    'total_target' => $todayTask->total_target,
                    'total_selesai' => $todayTask->total_selesai,
                    'sisa_kemarin' => $todayTask->sisa_kemarin,
                    'remaining' => $todayTask->remaining,
                    'progress_percentage' => $todayTask->progress_percentage,
                    'status' => $todayTask->status,
                    'is_completed' => $todayTask->is_completed,
                    'started_at' => $todayTask->started_at?->format('H:i'),
                    'completed_at' => $todayTask->completed_at?->format('H:i'),
                    'assignment_method' => $todayTask->assignment_method ?? 'target_only',
                ], $this->getBoxBasedAssignmentData($todayTask)) : null,
                'activeBox' => $activeBox ? [
                    'id' => $activeBox->id,
                    'kode_kerdus' => $activeBox->kode_kerdus,
                    'jumlah_terisi' => $activeBox->jumlah_terisi,
                    'kapasitas' => $activeBox->kapasitas,
                    'remaining_space' => $activeBox->remaining_space,
                    'progress_percentage' => $activeBox->progress_percentage,
                ] : null,
                'notifications' => $notifications,
                'performanceData' => $performanceData,
                'recentBoxes' => $recentBoxes,
                'sharedBoxes' => $sharedBoxes,
                'currentUser' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'currentTime' => now()->format('H:i'),
                'currentDate' => now()->format('d F Y'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Warehouse dashboard error: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat dashboard gudang: '.$e->getMessage());
        }
    }

    /**
     * Get or create today's task
     */
    private function getTodayTask($user)
    {
        $todayTask = DailyPackingTask::where('user_id', $user->id)
            ->whereDate('tanggal_tugas', today())
            ->with([
                'packingBoxes:id,daily_packing_task_id,kode_kerdus,status,jumlah_terisi,kapasitas',
                'taskItems:id,daily_packing_task_id,pengiriman_id,is_packed,packed_at',
            ])
            ->select('id', 'user_id', 'tanggal_tugas', 'total_target', 'total_selesai', 'sisa_kemarin', 'status', 'started_at', 'completed_at')
            ->first();

        // DISABLED: Auto-assignment removed - only supervisor can assign tasks now
        // Auto-assign disabled by user request - supervisor manual assignment only

        return $todayTask;
    }

    /**
     * Get performance data for dashboard
     */
    private function getPerformanceData($user)
    {
        // OPTIMIZED: Performance data with column selection
        $thisWeekTasks = DailyPackingTask::where('user_id', $user->id)
            ->whereBetween('tanggal_tugas', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->select('id', 'total_target', 'total_selesai', 'status', 'sisa_kemarin')
            ->get();

        // This month's performance
        $thisMonthTasks = DailyPackingTask::where('user_id', $user->id)
            ->whereMonth('tanggal_tugas', now()->month)
            ->whereYear('tanggal_tugas', now()->year)
            ->select('id', 'total_target', 'total_selesai', 'status', 'sisa_kemarin')
            ->get();

        return [
            'week' => [
                'total_target' => $thisWeekTasks->sum('total_target'),
                'total_achieved' => $thisWeekTasks->sum('total_selesai'),
                'completion_rate' => $thisWeekTasks->count() > 0
                    ? round($thisWeekTasks->where('is_completed', true)->count() / $thisWeekTasks->count() * 100, 2)
                    : 0,
                'days_worked' => $thisWeekTasks->count(),
            ],
            'month' => [
                'total_target' => $thisMonthTasks->sum('total_target'),
                'total_achieved' => $thisMonthTasks->sum('total_selesai'),
                'completion_rate' => $thisMonthTasks->count() > 0
                    ? round($thisMonthTasks->where('is_completed', true)->count() / $thisMonthTasks->count() * 100, 2)
                    : 0,
                'days_worked' => $thisMonthTasks->count(),
                'perfect_days' => $thisMonthTasks->where('is_completed', true)->where('sisa_kemarin', 0)->count(),
            ],
        ];
    }

    /**
     * Start scanning process
     */
    public function startScanning(Request $request)
    {
        $todayTask = DailyPackingTask::where('user_id', auth()->id())
            ->whereDate('tanggal_tugas', today())
            ->first();

        if (! $todayTask) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tugas untuk hari ini',
            ], 404);
        }

        if ($todayTask->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Tugas hari ini sudah selesai',
            ], 400);
        }

        // Start task if not started
        $todayTask->startTask();

        // OPTIMIZED: Get current box and assigned items with constrained eager loading
        $currentBox = $todayTask->getCurrentBox();
        $assignedItems = $todayTask->taskItems()
            ->where('is_packed', false)
            ->with(['pengiriman' => function ($query) {
                $query->select('id', 'no_resi', 'jumlah_quran', 'donatur_id', 'jenis_quran_id', 'wakaf_item_id')
                    ->with([
                        'donatur:id,nama_donatur',
                        'jenisQuran:id,nama_jenis',
                        'wakafItem:id,wakif_name',
                    ]);
            }])
            ->select('id', 'pengiriman_id')
            ->limit(20) // Next 20 items
            ->get();

        return response()->json([
            'success' => true,
            'task' => [
                'id' => $todayTask->id,
                'remaining' => $todayTask->remaining,
                'progress' => $todayTask->progress_percentage,
            ],
            'currentBox' => $currentBox ? [
                'id' => $currentBox->id,
                'kode_kerdus' => $currentBox->kode_kerdus,
                'remaining_space' => $currentBox->remaining_space,
            ] : null,
            'assignedItems' => $assignedItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'pengiriman' => [
                        'id' => $item->pengiriman->id,
                        'no_resi' => $item->pengiriman->no_resi,
                        'donatur' => $item->pengiriman->donatur->nama_donatur ?? 'Unknown',
                        'jenis_quran' => $item->pengiriman->jenisQuran->nama_jenis ?? 'Unknown',
                        'jumlah_quran' => $item->pengiriman->jumlah_quran,
                    ],
                ];
            }),
        ]);
    }

    /**
     * Get box-based assignment data for dashboard
     */
    private function getBoxBasedAssignmentData(DailyPackingTask $task): array
    {
        if (! $task || $task->assignment_method !== 'mixed_box_based') {
            return [];
        }

        $userId = $task->user_id;
        $data = [];

        // Get target breakdowns with progress
        $targetBreakdowns = $task->targetBreakdowns()
            ->with(['jenisQuran:id,nama_jenis,kode_jenis'])
            ->get()
            ->map(function ($breakdown) {
                return [
                    'id' => $breakdown->id,
                    'jenis_name' => $breakdown->jenisQuran->nama_jenis,
                    'jenis_badge_class' => $breakdown->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                    'target_boxes' => $breakdown->target_boxes,
                    'target_quantity' => $breakdown->target_quantity,
                    'completed_quantity' => $breakdown->completed_quantity,
                    'box_capacity' => $breakdown->box_capacity,
                    'is_shared_box' => $breakdown->is_shared_box,
                    'progress_percentage' => $breakdown->progress_percentage,
                ];
            });

        if ($targetBreakdowns->isNotEmpty()) {
            $data['target_breakdowns'] = $targetBreakdowns->toArray();
        }

        // Get individual boxes assigned to this user
        $individualBoxes = PackingBox::where('daily_packing_task_id', $task->id)
            ->where('assigned_user_id', $userId)
            ->where('assignment_type', 'individual')
            ->with(['jenisQuran:id,nama_jenis,kode_jenis'])
            ->get()
            ->map(function ($box) {
                return [
                    'id' => $box->id,
                    'kode_kerdus' => $box->kode_kerdus,
                    'status' => $box->status,
                    'jenis_name' => $box->jenisQuran->nama_jenis ?? 'Unknown',
                    'jenis_badge_class' => $box->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                    'jumlah_terisi' => $box->jumlah_terisi,
                    'kapasitas' => $box->kapasitas,
                    'progress_percentage' => $box->progress_percentage,
                ];
            });

        if ($individualBoxes->isNotEmpty()) {
            $data['individual_boxes'] = $individualBoxes->toArray();
        }

        // Get shared box allocations for this user
        $sharedAllocations = \App\Models\SharedBoxAssignment::where('daily_packing_task_id', $task->id)
            ->where('user_id', $userId)
            ->with(['packingBox.jenisQuran:id,nama_jenis,kode_jenis'])
            ->get()
            ->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'box_code' => $allocation->packingBox->kode_kerdus,
                    'jenis_name' => $allocation->packingBox->jenisQuran->nama_jenis ?? 'Unknown',
                    'jenis_badge_class' => $allocation->packingBox->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                    'allocated_items' => $allocation->allocated_items,
                    'completed_items' => $allocation->completed_items,
                    'progress_percentage' => $allocation->progress_percentage,
                    'started_at' => $allocation->started_at,
                    'completed_at' => $allocation->completed_at,
                    'is_completed' => $allocation->is_completed,
                ];
            });

        if ($sharedAllocations->isNotEmpty()) {
            $data['shared_allocations'] = $sharedAllocations->toArray();
        }

        return $data;
    }

    /**
     * Get shared boxes for collaboration interface
     */
    private function getSharedBoxesForCollaboration($user): array
    {
        // Get all shared boxes where this user has allocations OR that are related to their daily tasks
        $sharedBoxIds = \App\Models\SharedBoxAssignment::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->pluck('packing_box_id')
            ->unique();

        if ($sharedBoxIds->isEmpty()) {
            return [];
        }

        $sharedBoxes = \App\Models\PackingBox::whereIn('id', $sharedBoxIds)
            ->where('assignment_type', 'shared')
            ->with([
                'jenisQuran:id,nama_jenis,kode_jenis',
                'sharedBoxAssignments.user:id,name',
            ])
            ->get()
            ->map(function ($box) {
                $totalAllocated = $box->sharedBoxAssignments->sum('allocated_items');
                $totalCompleted = $box->sharedBoxAssignments->sum('completed_items');
                $overallProgress = $totalAllocated > 0 ? round(($totalCompleted / $totalAllocated) * 100, 2) : 0;

                return [
                    'id' => $box->id,
                    'kode_kerdus' => $box->kode_kerdus,
                    'jenis_name' => $box->jenisQuran->nama_jenis ?? 'Unknown',
                    'jenis_badge_class' => $box->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                    'total_allocated' => $totalAllocated,
                    'total_completed' => $totalCompleted,
                    'total_remaining' => $totalAllocated - $totalCompleted,
                    'overall_progress' => $overallProgress,
                    'contributors_count' => $box->sharedBoxAssignments->count(),
                    'active_contributors' => $box->sharedBoxAssignments->where('is_started', true)->where('is_completed', false)->count(),
                    'completed_contributors' => $box->sharedBoxAssignments->where('is_completed', true)->count(),
                    'contributors' => $box->sharedBoxAssignments->map(function ($assignment) {
                        return [
                            'user_id' => $assignment->user_id,
                            'user_name' => $assignment->user->name,
                            'allocated_items' => $assignment->allocated_items,
                            'completed_items' => $assignment->completed_items,
                            'progress_percentage' => $assignment->progress_percentage,
                            'is_started' => $assignment->is_started,
                            'is_completed' => $assignment->is_completed,
                            'started_at' => $assignment->started_at,
                            'completed_at' => $assignment->completed_at,
                            'contribution_rate' => $assignment->contribution_rate,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();

        return $sharedBoxes;
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead($id)
    {
        $notification = PackingNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if ($notification) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get shared boxes status for real-time collaboration
     */
    public function getSharedBoxesStatus()
    {
        $user = auth()->user();

        // Get shared boxes that this user is involved in or can see
        $sharedBoxes = PackingBox::where('assignment_type', 'shared')
            ->where('status', '!=', 'sealed')
            ->whereHas('sharedBoxAssignments', function ($query) use ($user) {
                // Either user is a contributor OR user is supervisor who can see all
                $query->where('user_id', $user->id)
                    ->orWhereExists(function ($subQuery) use ($user) {
                        $subQuery->select(DB::raw(1))
                            ->from('users')
                            ->where('id', $user->id)
                            ->role(['supervisor', 'super-admin']);
                    });
            })
            ->with([
                'jenisQuran:id,nama_jenis,kode_jenis',
                'sharedBoxAssignments.user:id,name',
                'dailyPackingTask:id,tanggal_tugas',
            ])
            ->get()
            ->map(function ($box) {
                $assignments = $box->sharedBoxAssignments;
                $boxProgress = \App\Models\SharedBoxAssignment::getTotalProgressForBox($box);

                return array_merge([
                    'id' => $box->id,
                    'kode_kerdus' => $box->kode_kerdus,
                    'jenis_name' => $box->jenisQuran->nama_jenis ?? 'Unknown',
                    'jenis_badge_class' => $box->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                    'contributors' => $assignments->map(function ($assignment) {
                        return [
                            'user_id' => $assignment->user_id,
                            'user_name' => $assignment->user->name,
                            'allocated_items' => $assignment->allocated_items,
                            'completed_items' => $assignment->completed_items,
                            'progress_percentage' => $assignment->progress_percentage,
                            'is_started' => $assignment->is_started,
                            'is_completed' => $assignment->is_completed,
                            'started_at' => $assignment->started_at,
                            'completed_at' => $assignment->completed_at,
                            'contribution_rate' => $assignment->contribution_rate,
                        ];
                    })->toArray(),
                ], $boxProgress);
            });

        return response()->json([
            'success' => true,
            'shared_boxes' => $sharedBoxes,
        ]);
    }

    /**
     * Show shared collaboration interface page
     */
    public function sharedCollaboration()
    {
        $user = auth()->user();

        // Get initial shared boxes data
        $sharedBoxes = $this->getSharedBoxesStatus()->getData()->shared_boxes;

        return Inertia::render('Warehouse/SharedCollaboration', [
            'sharedBoxes' => $sharedBoxes,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Get real-time dashboard status for auto-refresh
     */
    public function getDashboardStatus()
    {
        $user = auth()->user();

        try {
            // Get today's task with fresh data
            $todayTask = $this->getTodayTask($user);

            // Get active box info
            $activeBox = null;
            if ($todayTask && $todayTask->is_active) {
                $activeBox = $todayTask->getCurrentBox();
                if ($activeBox) {
                    $activeBox = [
                        'id' => $activeBox->id,
                        'kode_kerdus' => $activeBox->kode_kerdus,
                        'jumlah_terisi' => $activeBox->jumlah_terisi,
                        'kapasitas' => $activeBox->kapasitas,
                        'remaining_space' => $activeBox->remaining_space,
                        'progress_percentage' => $activeBox->progress_percentage,
                    ];
                }
            }

            // Get unread notifications count
            $notificationsCount = PackingNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            // Get shared boxes if user has shared allocations
            $sharedBoxes = $this->getSharedBoxesForCollaboration($user);

            $taskData = null;
            if ($todayTask) {
                $taskData = array_merge([
                    'id' => $todayTask->id,
                    'total_target' => $todayTask->total_target,
                    'total_selesai' => $todayTask->total_selesai,
                    'remaining' => $todayTask->remaining,
                    'progress_percentage' => $todayTask->progress_percentage,
                    'status' => $todayTask->status,
                    'is_completed' => $todayTask->is_completed,
                    'assignment_method' => $todayTask->assignment_method ?? 'target_only',
                ], $this->getBoxBasedAssignmentData($todayTask));
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'todayTask' => $taskData,
                    'activeBox' => $activeBox,
                    'notificationsCount' => $notificationsCount,
                    'sharedBoxes' => $sharedBoxes,
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard status error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch dashboard status',
            ], 500);
        }
    }

    /**
     * Get real-time progress for a specific box
     */
    public function getBoxProgress($boxId)
    {
        $user = auth()->user();

        try {
            $box = PackingBox::with([
                'jenisQuran:id,nama_jenis,kode_jenis',
                'sharedBoxAssignments.user:id,name',
            ])->find($boxId);

            if (! $box) {
                return response()->json([
                    'success' => false,
                    'error' => 'Box not found',
                ], 404);
            }

            // Check if user has access to this box
            $hasAccess = false;
            if ($box->assignment_type === 'individual' && $box->assigned_user_id === $user->id) {
                $hasAccess = true;
            } elseif ($box->assignment_type === 'shared') {
                $hasAccess = $box->sharedBoxAssignments()->where('user_id', $user->id)->exists() ||
                           $user->can('supervisor.warehouse.monitor');
            }

            if (! $hasAccess) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $boxData = [
                'id' => $box->id,
                'kode_kerdus' => $box->kode_kerdus,
                'status' => $box->status,
                'jenis_name' => $box->jenisQuran->nama_jenis ?? 'Unknown',
                'jenis_badge_class' => $box->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                'jumlah_terisi' => $box->jumlah_terisi,
                'kapasitas' => $box->kapasitas,
                'progress_percentage' => $box->progress_percentage,
                'assignment_type' => $box->assignment_type,
            ];

            // Add shared box specific data
            if ($box->assignment_type === 'shared') {
                $boxProgress = \App\Models\SharedBoxAssignment::getTotalProgressForBox($box);
                $boxData = array_merge($boxData, [
                    'total_allocated' => $boxProgress['total_allocated'],
                    'total_completed' => $boxProgress['total_completed'],
                    'overall_progress' => $boxProgress['overall_progress'],
                    'contributors_count' => $boxProgress['contributors_count'],
                    'active_contributors' => $boxProgress['active_contributors'],
                    'completed_contributors' => $boxProgress['completed_contributors'],
                    'contributors' => $box->sharedBoxAssignments->map(function ($assignment) {
                        return [
                            'user_id' => $assignment->user_id,
                            'user_name' => $assignment->user->name,
                            'allocated_items' => $assignment->allocated_items,
                            'completed_items' => $assignment->completed_items,
                            'progress_percentage' => $assignment->progress_percentage,
                            'is_started' => $assignment->is_started,
                            'is_completed' => $assignment->is_completed,
                            'started_at' => $assignment->started_at,
                            'completed_at' => $assignment->completed_at,
                            'contribution_rate' => $assignment->contribution_rate,
                        ];
                    })->toArray(),
                ]);
            }

            return response()->json([
                'success' => true,
                'box' => $boxData,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Box progress error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'box_id' => $boxId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch box progress',
            ], 500);
        }
    }

    /**
     * Trigger completion workflow for a shared box
     */
    public function triggerBoxCompletion($boxId)
    {
        $user = auth()->user();

        try {
            $box = PackingBox::find($boxId);

            if (! $box) {
                return response()->json([
                    'success' => false,
                    'error' => 'Box not found',
                ], 404);
            }

            // Check if user has access
            if ($box->assignment_type === 'shared') {
                $hasAccess = $box->sharedBoxAssignments()->where('user_id', $user->id)->exists() ||
                           $user->can('supervisor.warehouse.monitor');
            } else {
                $hasAccess = $box->assigned_user_id === $user->id ||
                           $user->can('supervisor.warehouse.monitor');
            }

            if (! $hasAccess) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            // Trigger completion workflow
            $result = \App\Models\SharedBoxAssignment::triggerCompletionWorkflow($box);

            return response()->json($result);

        } catch (\Exception $e) {
            \Log::error('Error triggering box completion: '.$e->getMessage(), [
                'user_id' => $user->id,
                'box_id' => $boxId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to trigger completion workflow',
            ], 500);
        }
    }

    /**
     * Request sealing for a completed shared box
     */
    public function requestBoxSealing($boxId)
    {
        $user = auth()->user();

        try {
            $box = PackingBox::with(['sharedBoxAssignments.user'])->find($boxId);

            if (! $box) {
                return response()->json([
                    'success' => false,
                    'error' => 'Box not found',
                ], 404);
            }

            // Check permissions
            $hasAccess = $box->sharedBoxAssignments()->where('user_id', $user->id)->exists() ||
                       $user->can('supervisor.warehouse.monitor');

            if (! $hasAccess) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            // Check if box is ready for sealing
            $completionStatus = \App\Models\SharedBoxAssignment::getBoxCompletionStatus($box);

            if (! $completionStatus['can_proceed_to_seal']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Box is not ready for sealing',
                    'completion_status' => $completionStatus,
                ], 400);
            }

            // Update box status to seal requested
            $box->update([
                'status' => 'seal_requested',
                'seal_requested_at' => now(),
                'seal_requested_by' => $user->id,
            ]);

            // Create notifications for supervisors
            $supervisors = \App\Models\User::permission('supervisor.warehouse.monitor')->get();

            foreach ($supervisors as $supervisor) {
                PackingNotification::create([
                    'user_id' => $supervisor->id,
                    'daily_packing_task_id' => $box->sharedBoxAssignments()->first()->daily_packing_task_id,
                    'type' => 'seal_request',
                    'level' => 'warning',
                    'title' => 'Seal Request - Shared Box',
                    'message' => "📦 {$user->name} meminta sealing untuk shared box {$box->kode_kerdus}",
                    'meta_data' => [
                        'box_code' => $box->kode_kerdus,
                        'requested_by' => $user->name,
                        'requested_at' => now()->format('H:i'),
                        'total_items' => $box->sharedBoxAssignments->sum('completed_items'),
                        'action_required' => 'approve_seal',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sealing request submitted successfully',
                'box_status' => 'seal_requested',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error requesting box sealing: '.$e->getMessage(), [
                'user_id' => $user->id,
                'box_id' => $boxId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to request sealing',
            ], 500);
        }
    }

    /**
     * Get boxes that are ready for sealing
     */
    public function getBoxesReadyForSeal()
    {
        $user = auth()->user();

        try {
            // Get boxes that are ready for sealing based on user role
            $query = PackingBox::whereIn('status', ['ready_to_seal', 'seal_requested'])
                ->with(['jenisQuran:id,nama_jenis,kode_jenis', 'sharedBoxAssignments.user:id,name']);

            // Filter based on user permissions - supervisors can see all boxes
            if (!$user->can('supervisor.warehouse.monitor')) {
                // Regular users can only see their own boxes or shared boxes they contributed to
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_user_id', $user->id)
                        ->orWhereHas('sharedBoxAssignments', function ($subQ) use ($user) {
                            $subQ->where('user_id', $user->id);
                        });
                });
            }

            $boxes = $query->get()->map(function ($box) {
                $completionStatus = \App\Models\SharedBoxAssignment::getBoxCompletionStatus($box);

                return [
                    'id' => $box->id,
                    'kode_kerdus' => $box->kode_kerdus,
                    'status' => $box->status,
                    'jenis_name' => $box->jenisQuran->nama_jenis ?? 'Unknown',
                    'jenis_badge_class' => $box->jenisQuran->badge_class ?? 'bg-gray-100 text-gray-800',
                    'assignment_type' => $box->assignment_type,
                    'total_items' => $box->assignment_type === 'shared'
                        ? $box->sharedBoxAssignments->sum('completed_items')
                        : $box->jumlah_terisi,
                    'capacity' => $box->kapasitas,
                    'completion_status' => $completionStatus,
                    'contributors' => $box->assignment_type === 'shared'
                        ? $box->sharedBoxAssignments->map(function ($assignment) {
                            return [
                                'user_name' => $assignment->user->name,
                                'completed_items' => $assignment->completed_items,
                                'allocated_items' => $assignment->allocated_items,
                            ];
                        })->toArray()
                        : [],
                    'ready_to_seal_at' => $box->completion_triggered_at,
                    'seal_requested_at' => $box->seal_requested_at,
                ];
            });

            return response()->json([
                'success' => true,
                'boxes' => $boxes,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching boxes ready for seal: '.$e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch boxes ready for sealing',
            ], 500);
        }
    }
}
