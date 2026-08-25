<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use App\Models\PackingItem;
use App\Models\Pengiriman;
use App\Services\ConcurrencyMonitorService;
use App\Services\PackingAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PackingController extends Controller
{
    protected $assignmentService;

    protected $concurrencyMonitor;

    public function __construct(
        PackingAssignmentService $assignmentService,
        ConcurrencyMonitorService $concurrencyMonitor
    ) {
        $this->assignmentService = $assignmentService;
        $this->concurrencyMonitor = $concurrencyMonitor;
    }

    /**
     * Display packing page (TARGET-ONLY SYSTEM)
     */
    public function index()
    {
        $user = auth()->user();

        // Additional authorization check
        Gate::authorize('viewPacking', $user);

        // OPTIMIZED: Get today's task with constrained eager loading
        $todayTask = DailyPackingTask::where('user_id', $user->id)
            ->whereDate('tanggal_tugas', today())
            ->with(['packingBoxes' => function ($query) {
                $query->select('id', 'daily_packing_task_id', 'kode_kerdus', 'status', 'jenis_quran_id', 'jumlah_terisi', 'kapasitas')
                    ->with('jenisQuran:id,nama_jenis,kode_jenis')
                    ->orderBy('id');
            }])
            ->select('id', 'user_id', 'tanggal_tugas', 'total_target', 'total_selesai', 'status')
            ->first();

        if (! $todayTask) {
            // No task assigned today - show empty state
            return Inertia::render('Warehouse/Packing', [
                'task' => null,
                'currentBox' => null,
                'allBoxes' => [],
                'nextItems' => [],
                'packedItems' => [],
                'noTaskMessage' => 'Tidak ada tugas packing untuk hari ini. Silakan hubungi supervisor untuk mendapatkan tugas.',
            ]);
        }

        if ($todayTask->is_completed) {
            return redirect()->route('admin.warehouse.dashboard')
                ->with('success', 'Tugas hari ini sudah selesai!');
        }

        // Start task if not started
        if ($todayTask->status === 'assigned') {
            $todayTask->startTask();
        }

        // OPTIMIZED: Get current box with jenis info (may be null for first scan)
        $currentBox = $todayTask->packingBoxes()
            ->where('status', 'filling')
            ->with('jenisQuran:id,nama_jenis,kode_jenis')
            ->select('id', 'kode_kerdus', 'status', 'jenis_quran_id', 'jumlah_terisi', 'kapasitas', 'daily_packing_task_id')
            ->first();

        // Note: currentBox can be null when starting fresh - box will be created on first scan

        // OPTIMIZED: Get user's assigned items with constrained eager loading
        $assignedItems = $todayTask->taskItems()
            ->with(['pengiriman' => function ($query) {
                $query->select('id', 'no_resi', 'jumlah_quran', 'nama_penerima', 'alamat_tujuan', 'donatur_id', 'jenis_quran_id', 'wakaf_item_id', 'status_id')
                    ->with([
                        'donatur:id,nama_donatur',
                        'jenisQuran:id,nama_jenis',
                        'wakafItem:id,wakif_name',
                        'status:id,nama',
                    ]);
            }])
            ->select('id', 'pengiriman_id', 'is_packed', 'packed_at', 'assigned_at')
            ->orderBy('is_packed')
            ->orderBy('assigned_at')
            ->get();

        // Get available pengiriman for free-pick (show sample of what's available)
        $availablePengiriman = $this->assignmentService->getAvailablePengiriman(20);
        $availableCount = $this->assignmentService->getAvailablePengirimanCount();

        // OPTIMIZED: Get packed items in current box with constrained eager loading
        $packedItems = $currentBox ? $currentBox->packingItems()
            ->with(['pengiriman' => function ($query) {
                $query->select('id', 'no_resi', 'jumlah_quran', 'donatur_id', 'jenis_quran_id')
                    ->with([
                        'donatur:id,nama_donatur',
                        'jenisQuran:id,nama_jenis',
                    ]);
            }])
            ->select('id', 'pengiriman_id', 'urutan_dalam_box', 'packed_at')
            ->orderBy('urutan_dalam_box', 'desc')
            ->get() : collect();

        return Inertia::render('Warehouse/Packing', [
            'task' => [
                'id' => $todayTask->id,
                'total_target' => $todayTask->total_target,
                'total_selesai' => $todayTask->total_selesai,
                'remaining' => $todayTask->remaining,
                'progress_percentage' => $todayTask->progress_percentage,
            ],
            'currentBox' => $currentBox ? [
                'id' => $currentBox->id,
                'kode_kerdus' => $currentBox->kode_kerdus,
                'jumlah_terisi' => $currentBox->jumlah_terisi,
                'kapasitas' => $currentBox->kapasitas,
                'remaining_space' => $currentBox->remaining_space,
                'progress_percentage' => $currentBox->progress_percentage,
                'status' => $currentBox->status,
                'jenis_info' => $currentBox->jenisQuran ? [
                    'id' => $currentBox->jenisQuran->id,
                    'nama_jenis' => $currentBox->jenisQuran->nama_jenis,
                    'kode_jenis' => $currentBox->jenisQuran->kode_jenis,
                    'badge_class' => $currentBox->jenisQuran->badge_class,
                    'description' => $currentBox->jenisQuran->capacity_info['description'],
                    'unit' => match ($currentBox->jenisQuran->kode_jenis) {
                        'A5' => 'eksemplar A5',
                        'A6' => 'eksemplar A6',
                        'IQRO' => 'buku Iqro',
                        default => 'mushaf'
                    },
                ] : null,
            ] : null,
            'allBoxes' => $todayTask->packingBoxes->map(function ($box) {
                return [
                    'id' => $box->id,
                    'kode_kerdus' => $box->kode_kerdus,
                    'status' => $box->status,
                    'jumlah_terisi' => $box->jumlah_terisi,
                    'kapasitas' => $box->kapasitas,
                    'is_current' => $box->status === 'filling',
                ];
            }),
            'assignedItems' => $assignedItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'is_packed' => $item->is_packed,
                    'packed_at' => $item->packed_at?->format('H:i:s'),
                    'pengiriman' => [
                        'id' => $item->pengiriman->id,
                        'no_resi' => $item->pengiriman->no_resi,
                        'donatur' => $item->pengiriman->donatur->nama_donatur ?? 'Unknown',
                        'wakif' => $item->pengiriman->wakafItem->wakif_name ??
                                  ($item->pengiriman->donatur->nama_donatur ?? 'Unknown'),
                        'jenis_quran' => $item->pengiriman->jenisQuran->nama_jenis ?? 'Unknown',
                        'jumlah_quran' => $item->pengiriman->jumlah_quran,
                        'nama_penerima' => $item->pengiriman->nama_penerima ?? '',
                        'alamat_tujuan' => $item->pengiriman->alamat_tujuan ?? '',
                        'status' => $item->pengiriman->status->nama ?? 'Unknown',
                    ],
                ];
            }),
            'availablePengiriman' => $availablePengiriman->map(function ($pengiriman) {
                return [
                    'id' => $pengiriman->id,
                    'no_resi' => $pengiriman->no_resi,
                    'donatur' => $pengiriman->donatur->nama_donatur ?? 'Unknown',
                    'wakif' => $pengiriman->wakafItem->wakif_name ??
                              ($pengiriman->donatur->nama_donatur ?? 'Unknown'),
                    'jenis_quran' => $pengiriman->jenisQuran->nama_jenis ?? 'Unknown',
                    'jumlah_quran' => $pengiriman->jumlah_quran,
                    'created_at' => $pengiriman->created_at->format('Y-m-d H:i'),
                ];
            }),
            'availableCount' => $availableCount,
            'packedItems' => $packedItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'urutan' => $item->urutan_dalam_box,
                    'packed_at' => $item->packed_at->format('H:i:s'),
                    'pengiriman' => [
                        'no_resi' => $item->pengiriman->no_resi,
                        'donatur' => $item->pengiriman->donatur->nama_donatur ?? 'Unknown',
                        'jumlah_quran' => $item->pengiriman->jumlah_quran,
                    ],
                ];
            }),
        ]);
    }

    /**
     * Scan and pack item (FREE-PICK SYSTEM)
     */
    public function scanItem(Request $request)
    {
        // Enhanced input validation with security measures
        $request->validate([
            'qr_data' => [
                'required',
                'string',
                'max:500', // Prevent overly long inputs
                'regex:/^[A-Za-z0-9\-\{\}\":\s,_]+$/', // Allow only safe characters
            ],
        ]);

        // Authorization check (graceful JSON response instead of 403 page)
        if (! Gate::allows('scanItems', auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Akses scan ditolak: tidak ada tugas aktif atau tidak memiliki izin.',
                'type' => 'unauthorized',
            ], 200);
        }

        // Log scan attempt for audit trail
        Log::info('Item scan attempt', [
            'user_id' => auth()->id(),
            'qr_data_length' => strlen($request->qr_data),
            'ip_address' => $request->ip(),
        ]);

        // Record concurrent access attempt
        $startTime = microtime(true);
        $this->concurrencyMonitor->recordConcurrentAccess(
            'item_packing',
            auth()->id(),
            'scan_item'
        );

        // Implement retry mechanism with exponential backoff for race condition handling
        $maxRetries = 3;
        $baseDelay = 100; // Base delay in milliseconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return DB::transaction(function () use ($request, $startTime) {
                    // Use default DB isolation level; rely on explicit row locking

                    // Sanitize input
                    $sanitizedQrData = trim(strip_tags($request->qr_data));

                    // Parse QR data (could be JSON or plain resi)
                    $noResi = $this->parseQRData($sanitizedQrData);

                    if (! $noResi) {
                        // Log invalid QR code attempts for security monitoring
                        Log::warning('Invalid QR code scan attempt', [
                            'user_id' => auth()->id(),
                            'qr_data' => substr($sanitizedQrData, 0, 100), // Log only first 100 chars for security
                            'ip_address' => request()->ip(),
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'QR Code tidak valid',
                            'type' => 'invalid_qr',
                        ], 200);
                    }

                    // OPTIMIZED: Find pengiriman with lock and required columns
                    $pengiriman = Pengiriman::where('no_resi', $noResi)
                        ->with('jenisQuran:id,nama_jenis')
                        ->select('id', 'no_resi', 'jenis_quran_id', 'status_id', 'jumlah_quran')
                        ->lockForUpdate()
                        ->first();

                    if (! $pengiriman) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Resi tidak ditemukan: '.$noResi,
                            'type' => 'not_found',
                        ], 200);
                    }

                    // Check if already packed with lock
                    $alreadyPacked = PackingItem::where('pengiriman_id', $pengiriman->id)
                        ->lockForUpdate()
                        ->exists();
                    if ($alreadyPacked) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Item sudah dipacking sebelumnya',
                            'type' => 'already_packed',
                        ], 200);
                    }

                    // Get user's task with lock
                    $todayTask = DailyPackingTask::where('user_id', auth()->id())
                        ->whereDate('tanggal_tugas', today())
                        ->lockForUpdate()
                        ->first();

                    if (! $todayTask) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Tidak ada tugas packing untuk hari ini. Silakan hubungi supervisor.',
                            'type' => 'no_task',
                        ], 200);
                    }

                    // Check box status for warning system BEFORE assignment
                    $boxStatus = $todayTask->getBoxStatusForJenis($pengiriman->jenis_quran_id);

                    // If warning needed (ada box jenis lain yang aktif)
                    if ($boxStatus['warning_needed']) {
                        $otherBoxes = $boxStatus['other_active_boxes']->map(function ($box) {
                            return "{$box->jenisQuran->nama_jenis} ({$box->jumlah_terisi}/{$box->kapasitas})";
                        })->implode(', ');

                        return response()->json([
                            'success' => false,
                            'message' => "⚠️ PERINGATAN JENIS BERBEDA!\n\nAnda akan scan {$pengiriman->jenisQuran->nama_jenis}, tapi ada box aktif jenis lain:\n{$otherBoxes}\n\nApakah tetap lanjut? Box lama akan di-seal otomatis.",
                            'type' => 'jenis_warning',
                            'data' => [
                                'current_jenis' => $pengiriman->jenisQuran->nama_jenis,
                                'other_boxes' => $boxStatus['other_active_boxes']->toArray(),
                                'pengiriman_id' => $pengiriman->id,
                            ],
                        ], 200); // 200 karena bukan error, tapi warning
                    }

                    // FREE-PICK SYSTEM: Now assign this pengiriman to user (no warning needed)
                    try {
                        $taskItem = $this->assignmentService->assignPengirimanOnScan(
                            auth()->user(),
                            $pengiriman
                        );
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'message' => $e->getMessage(),
                            'type' => 'assignment_error',
                        ], 200);
                    }

                    // SMART BOX SELECTION: Choose box based on assignment method
                    $box = $this->getSmartBoxSelection($todayTask, $pengiriman, auth()->id());

                    if (! $box) {
                        return response()->json([
                            'success' => false,
                            'message' => "❌ GAGAL MENDAPATKAN KERDUS!\n\nTidak dapat menemukan atau membuat kerdus untuk jenis: {$pengiriman->jenisQuran->nama_jenis}\n\nSilakan hubungi supervisor.",
                            'type' => 'box_selection_failed',
                        ], 200);
                    }

                    // Add item to box with enhanced safety checks
                    try {
                        $packingItem = $box->addItemSafe($pengiriman, auth()->id());
                    } catch (\Exception $e) {
                        // Treat capacity/jenis conflicts as business errors (not 500)
                        Log::warning('Box add item failed', [
                            'user_id' => auth()->id(),
                            'box_id' => $box->id ?? null,
                            'pengiriman_id' => $pengiriman->id,
                            'message' => $e->getMessage(),
                        ]);

                        return response()->json([
                            'success' => false,
                            'type' => 'box_add_error',
                            'message' => $e->getMessage(),
                        ], 200);
                    }

                    // Mark task item as packed
                    $taskItem->update([
                        'is_packed' => true,
                        'packed_at' => now(),
                    ]);

                    // Update progress tracking based on assignment method
                    $task = $taskItem->dailyPackingTask;
                    $this->updateProgressTracking($task, $box, $pengiriman, auth()->id());

                    // Update daily task progress
                    $task->incrementProgress();

                    // Note: Status tetap "packing" - admin akan update manual ke "dokumentasi"

                    // Check if box is now full
                    $boxFull = $box->fresh()->is_full;

                    // Record successful transaction for monitoring (non-fatal)
                    try {
                        $executionTime = microtime(true) - $startTime;
                        $this->concurrencyMonitor->recordSuccessfulTransaction(
                            'item_packing',
                            auth()->id(),
                            $executionTime
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Failed to record packing success metrics', [
                            'user_id' => auth()->id(),
                            'error' => $e->getMessage(),
                        ]);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => "✅ BERHASIL DIPACKING!\n\n📦 {$pengiriman->jenisQuran->nama_jenis}\n🏷️ Kerdus: {$box->kode_kerdus}",
                        'data' => [
                            'no_resi' => $pengiriman->no_resi,
                            'jenis' => $pengiriman->jenisQuran->nama_jenis,
                            'box' => [
                                'kode_kerdus' => $box->kode_kerdus,
                                'jenis' => $box->jenisQuran->nama_jenis ?? 'Belum ditentukan',
                                'jumlah_terisi' => $box->jumlah_terisi,
                                'kapasitas' => $box->kapasitas,
                                'is_full' => $boxFull,
                            ],
                            'task' => [
                                'total_selesai' => $task->total_selesai,
                                'remaining' => $task->remaining,
                                'progress' => $task->progress_percentage,
                                'is_completed' => $task->fresh()->is_completed,
                            ],
                        ],
                        'box_full' => $boxFull,
                        'task_completed' => $task->fresh()->is_completed,
                    ]);
                }, 5); // 5 attempts for deadlock retry

            } catch (\Illuminate\Database\QueryException $e) {
                // Record failed transaction for monitoring
                $this->concurrencyMonitor->recordFailedTransaction(
                    'item_packing',
                    auth()->id(),
                    $e->getMessage(),
                    $attempt
                );
                // Handle database-specific errors (deadlocks, timeouts)
                if ($e->getCode() == '40001' || str_contains($e->getMessage(), 'Deadlock found')) {
                    Log::warning("Database deadlock detected on attempt {$attempt}", [
                        'user_id' => auth()->id(),
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    // If this is not the last attempt, wait and retry
                    if ($attempt < $maxRetries) {
                        $delay = $baseDelay * pow(2, $attempt - 1); // Exponential backoff
                        usleep($delay * 1000); // Convert to microseconds

                        continue;
                    }
                }

                // Not a retryable error or max retries reached
                throw $e;
            } catch (\Exception $e) {
                // Non-database errors should not be retried
                Log::error('Non-retryable packing scan error', [
                    'user_id' => auth()->id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Return as business error to keep frontend UX consistent
                return response()->json([
                    'success' => false,
                    'type' => 'unexpected_error',
                    'message' => 'Gagal memproses scan: '.$e->getMessage(),
                ], 200);
            }
        }

        // If we reach here, all retry attempts failed
        Log::error('All retry attempts failed for item scan', [
            'user_id' => auth()->id(),
            'max_retries' => $maxRetries,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Sistem sedang sibuk. Silakan coba lagi dalam beberapa saat.',
            'type' => 'retry_exhausted',
        ], 200);
    }

    /**
     * Process scan with jenis confirmation (after warning)
     */
    public function processWithJenisConfirmation(Request $request)
    {
        // Enhanced input validation
        $request->validate([
            'pengiriman_id' => 'required|integer|exists:pengiriman,id',
            'confirm_seal_others' => 'required|boolean',
        ]);

        // Authorization check (graceful JSON response)
        if (! Gate::allows('scanItems', auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Akses scan ditolak: tidak ada tugas aktif atau tidak memiliki izin.',
                'type' => 'unauthorized',
            ], 200);
        }

        // Log confirmation process for audit trail
        Log::info('Jenis confirmation process initiated', [
            'user_id' => auth()->id(),
            'pengiriman_id' => $request->pengiriman_id,
            'confirm_seal_others' => $request->confirm_seal_others,
            'ip_address' => $request->ip(),
        ]);

        if (! $request->confirm_seal_others) {
            return response()->json([
                'success' => false,
                'message' => 'Scan dibatalkan',
                'type' => 'cancelled',
            ], 200);
        }

        DB::beginTransaction();
        try {
            $pengiriman = Pengiriman::with('jenisQuran')->find($request->pengiriman_id);

            // Get today's task
            $todayTask = DailyPackingTask::where('user_id', auth()->id())
                ->whereDate('tanggal_tugas', today())
                ->where('status', '!=', DailyPackingTask::STATUS_EXPIRED)
                ->first();

            if (! $todayTask) {
                throw new \Exception('Tidak ada tugas aktif untuk hari ini');
            }

            // Seal all other active boxes
            $otherActiveBoxes = $todayTask->getActiveBoxesOtherThanJenis($pengiriman->jenis_quran_id);
            foreach ($otherActiveBoxes as $box) {
                $box->seal();
            }

            // Try to assign this pengiriman to user (if not already assigned)
            try {
                $taskItem = $this->assignmentService->assignPengirimanOnScan(
                    auth()->user(),
                    $pengiriman
                );
            } catch (\Exception $e) {
                throw new \Exception('Gagal assign pengiriman: '.$e->getMessage());
            }

            // Get or create box for this jenis
            $box = $todayTask->getOrActivateBoxForJenis($pengiriman->jenis_quran_id);

            if (! $box) {
                throw new \Exception('Gagal membuat box untuk jenis ini');
            }

            // Add item to box
            $packingItem = $box->addItem($pengiriman, auth()->id());

            // Mark task item as packed
            $taskItem->update([
                'is_packed' => true,
                'packed_at' => now(),
            ]);

            // Update task progress
            $todayTask->incrementProgress();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "✅ Berhasil!\n\n{$pengiriman->kode_pengiriman} → {$box->kode_kerdus}\nJenis: {$pengiriman->jenisQuran->nama_jenis}\nBox: {$box->jumlah_terisi}/{$box->kapasitas}",
                'data' => [
                    'box' => $box->fresh(),
                    'task_progress' => [
                        'completed' => $todayTask->fresh()->total_selesai,
                        'target' => $todayTask->total_target,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Seal box
     */
    public function sealBox(PackingBox $box)
    {
        // Authorization check for sealing specific box
        Gate::authorize('sealBox', $box);

        // Log box sealing attempt for audit trail
        Log::info('Box sealing initiated', [
            'user_id' => auth()->id(),
            'box_id' => $box->id,
            'box_code' => $box->kode_kerdus,
            'box_status' => $box->status,
            'assignment_type' => $box->assignment_type,
            'items_count' => $box->jumlah_terisi,
            'ip_address' => request()->ip(),
        ]);

        if ($box->is_sealed) {
            return response()->json([
                'success' => false,
                'message' => 'Kerdus sudah tersegel',
            ], 400);
        }

        // Enhanced validation for shared boxes
        if ($box->assignment_type === 'shared') {
            return $this->sealSharedBox($box);
        }

        // Regular box validation
        if ($box->jumlah_terisi === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Kerdus masih kosong',
            ], 400);
        }

        try {
            $box->seal();

            // Log successful sealing
            Log::info('Box sealed successfully', [
                'user_id' => auth()->id(),
                'box_id' => $box->id,
                'box_code' => $box->kode_kerdus,
                'seal_code' => $box->seal_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kerdus berhasil disegel',
                'seal_code' => $box->seal_code,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyegel kerdus: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Seal shared box with completion workflow validation
     */
    private function sealSharedBox(PackingBox $box)
    {
        $user = auth()->user();

        try {
            // Load box with assignments
            $box->load(['sharedBoxAssignments.user']);

            // Check completion status
            $completionStatus = \App\Models\SharedBoxAssignment::getBoxCompletionStatus($box);

            if (! $completionStatus['can_proceed_to_seal']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shared box belum siap untuk di-seal',
                    'completion_status' => $completionStatus,
                    'validation_errors' => [
                        'contributors_completed' => $completionStatus['completed_contributors'],
                        'total_contributors' => $completionStatus['total_contributors'],
                        'capacity_filled' => $completionStatus['capacity_filled'],
                    ],
                ], 400);
            }

            // Check if box status is appropriate for sealing
            $validStatuses = ['ready_to_seal', 'seal_requested', 'filling'];
            if (! in_array($box->status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status box tidak valid untuk sealing: '.$box->status,
                ], 400);
            }

            // Get total items from assignments
            $totalItems = $box->sharedBoxAssignments->sum('completed_items');

            if ($totalItems === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shared box masih kosong',
                ], 400);
            }

            // Update box jumlah_terisi to reflect actual shared contributions
            $box->update(['jumlah_terisi' => $totalItems]);

            // Seal the box
            $box->seal();

            // Create sealing notifications for all contributors
            $this->notifySharedBoxSealed($box);

            // Log successful shared box sealing
            Log::info('Shared box sealed successfully', [
                'user_id' => $user->id,
                'box_id' => $box->id,
                'box_code' => $box->kode_kerdus,
                'seal_code' => $box->seal_code,
                'total_items' => $totalItems,
                'contributors' => $box->sharedBoxAssignments->pluck('user.name')->toArray(),
                'completion_status' => $completionStatus,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shared box berhasil di-seal!',
                'seal_code' => $box->seal_code,
                'total_items' => $totalItems,
                'contributors_count' => $box->sharedBoxAssignments->count(),
                'completion_status' => $completionStatus,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sealing shared box: '.$e->getMessage(), [
                'user_id' => $user->id,
                'box_id' => $box->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyegel shared box: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Notify all contributors when shared box is sealed
     */
    private function notifySharedBoxSealed(PackingBox $box)
    {
        foreach ($box->sharedBoxAssignments as $assignment) {
            \App\Models\PackingNotification::create([
                'user_id' => $assignment->user_id,
                'daily_packing_task_id' => $assignment->daily_packing_task_id,
                'type' => 'shared_box_sealed',
                'level' => 'success',
                'title' => 'Shared Box Tersegel!',
                'message' => "🎉 Shared box {$box->kode_kerdus} telah berhasil di-seal dengan kode: {$box->seal_code}",
                'meta_data' => [
                    'box_code' => $box->kode_kerdus,
                    'seal_code' => $box->seal_code,
                    'total_items' => $box->sharedBoxAssignments->sum('completed_items'),
                    'your_contribution' => $assignment->completed_items,
                    'sealed_at' => now()->format('H:i'),
                    'sealed_by' => auth()->user()->name,
                ],
            ]);
        }

        // Also notify supervisors
        $supervisors = \App\Models\User::permission('supervisor.warehouse.monitor')->get();

        foreach ($supervisors as $supervisor) {
            \App\Models\PackingNotification::create([
                'user_id' => $supervisor->id,
                'daily_packing_task_id' => $box->sharedBoxAssignments->first()->daily_packing_task_id,
                'type' => 'shared_box_sealed_supervisor',
                'level' => 'info',
                'title' => 'Shared Box Sealed - Report',
                'message' => "📦 Shared box {$box->kode_kerdus} telah di-seal oleh {auth()->user()->name}",
                'meta_data' => [
                    'box_code' => $box->kode_kerdus,
                    'seal_code' => $box->seal_code,
                    'total_items' => $box->sharedBoxAssignments->sum('completed_items'),
                    'contributors' => $box->sharedBoxAssignments->pluck('user.name')->toArray(),
                    'sealed_by' => auth()->user()->name,
                    'sealed_at' => now()->format('H:i'),
                ],
            ]);
        }
    }

    /**
     * View packing history
     */
    public function history()
    {
        $user = auth()->user();

        // Authorization check for viewing history
        Gate::authorize('viewOwnHistory', $user);

        // Log history access for audit trail
        Log::info('Packing history accessed', [
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);

        // OPTIMIZED: History with column selection and relationship counts
        $history = DailyPackingTask::where('user_id', $user->id)
            ->with(['packingBoxes' => function ($query) {
                $query->select('id', 'daily_packing_task_id', 'kode_kerdus', 'status', 'jumlah_terisi', 'kapasitas', 'sealed_at')
                    ->withCount('packingItems');
            }])
            ->select('id', 'user_id', 'tanggal_tugas', 'total_target', 'total_selesai', 'status', 'started_at', 'completed_at')
            ->orderBy('tanggal_tugas', 'desc')
            ->paginate(10);

        return Inertia::render('Warehouse/PackingHistory', [
            'history' => $history,
        ]);
    }

    /**
     * Update progress tracking for box-based assignments
     */
    private function updateProgressTracking(DailyPackingTask $task, PackingBox $box, Pengiriman $pengiriman, int $userId): void
    {
        $assignmentMethod = $task->assignment_method ?? 'target_only';

        if ($assignmentMethod !== 'mixed_box_based') {
            return; // Legacy system - no additional tracking needed
        }

        $jenisQuranId = $pengiriman->jenis_quran_id;
        $quantity = $pengiriman->jumlah_quran ?? 1;

        // Update target breakdown progress
        $targetBreakdown = \App\Models\DailyPackingTaskTarget::where('daily_packing_task_id', $task->id)
            ->where('jenis_quran_id', $jenisQuranId)
            ->first();

        if ($targetBreakdown) {
            $targetBreakdown->increment('completed_quantity', $quantity);

            Log::info('Updated target breakdown progress', [
                'target_id' => $targetBreakdown->id,
                'jenis_quran_id' => $jenisQuranId,
                'completed_quantity' => $targetBreakdown->fresh()->completed_quantity,
                'target_quantity' => $targetBreakdown->target_quantity,
            ]);
        }

        // Update shared box allocation if applicable
        if ($box->assignment_type === 'shared') {
            $sharedAllocation = \App\Models\SharedBoxAssignment::where('packing_box_id', $box->id)
                ->where('user_id', $userId)
                ->first();

            if ($sharedAllocation && $sharedAllocation->can_contribute) {
                $success = $sharedAllocation->incrementProgress($quantity);

                if ($success) {
                    Log::info('Updated shared box allocation progress', [
                        'allocation_id' => $sharedAllocation->id,
                        'user_id' => $userId,
                        'box_id' => $box->id,
                        'completed_items' => $sharedAllocation->fresh()->completed_items,
                        'allocated_items' => $sharedAllocation->allocated_items,
                    ]);
                }
            }
        }

        // Update task-level box counters
        if ($box->is_full && $box->status === 'sealed') {
            $task->increment('total_boxes_completed');

            Log::info('Task box completion counter updated', [
                'task_id' => $task->id,
                'total_boxes_completed' => $task->fresh()->total_boxes_completed,
                'sealed_box_id' => $box->id,
            ]);
        }
    }

    /**
     * Smart box selection based on assignment method and user allocations
     */
    private function getSmartBoxSelection(DailyPackingTask $task, Pengiriman $pengiriman, int $userId): ?PackingBox
    {
        $jenisQuranId = $pengiriman->jenis_quran_id;
        $assignmentMethod = $task->assignment_method ?? 'target_only';

        // Log box selection attempt for monitoring
        Log::info('Smart box selection initiated', [
            'user_id' => $userId,
            'task_id' => $task->id,
            'jenis_quran_id' => $jenisQuranId,
            'assignment_method' => $assignmentMethod,
        ]);

        // Handle different assignment methods
        switch ($assignmentMethod) {
            case 'mixed_box_based':
                return $this->selectBoxForMixedAssignment($task, $jenisQuranId, $userId);

            case 'target_only':
            case 'flat':
            default:
                // Legacy system - use existing logic
                return $task->getOrActivateBoxForJenis($jenisQuranId);
        }
    }

    /**
     * Select box for mixed box-based assignment (individual + shared boxes)
     */
    private function selectBoxForMixedAssignment(DailyPackingTask $task, int $jenisQuranId, int $userId): ?PackingBox
    {
        return DB::transaction(function () use ($task, $jenisQuranId, $userId) {
            // Use default isolation; rely on row-level locks

            // First Priority: Find individual box assigned to this user for this jenis
            $individualBox = PackingBox::where('daily_packing_task_id', $task->id)
                ->where('jenis_quran_id', $jenisQuranId)
                ->where('assigned_user_id', $userId)
                ->where('assignment_type', 'individual')
                ->whereIn('status', ['empty', 'filling'])
                ->lockForUpdate()
                ->first();

            if ($individualBox) {
                // Activate box if empty
                if ($individualBox->status === 'empty') {
                    $individualBox->update(['status' => 'filling']);

                    // Record first scan time on task if not already set
                    if (! $task->first_scan_at) {
                        $task->update(['first_scan_at' => now()]);
                    }
                }

                Log::info('Selected individual box', [
                    'box_id' => $individualBox->id,
                    'box_code' => $individualBox->kode_kerdus,
                    'user_id' => $userId,
                ]);

                return $individualBox;
            }

            // Second Priority: Find shared box where user has allocation
            $sharedAllocation = \App\Models\SharedBoxAssignment::with(['packingBox' => function ($query) {
                $query->where('status', '!=', 'sealed');
            }])
                ->where('daily_packing_task_id', $task->id)
                ->where('user_id', $userId)
                ->whereHas('packingBox', function ($query) use ($jenisQuranId) {
                    $query->where('jenis_quran_id', $jenisQuranId)
                        ->where('assignment_type', 'shared')
                        ->whereIn('status', ['empty', 'filling']);
                })
                ->where('completed_items', '<', DB::raw('allocated_items'))
                ->lockForUpdate()
                ->first();

            if ($sharedAllocation && $sharedAllocation->packingBox) {
                $sharedBox = $sharedAllocation->packingBox;

                // Activate shared box if empty
                if ($sharedBox->status === 'empty') {
                    $sharedBox->update(['status' => 'filling']);

                    // Set started_at for this user's allocation
                    if (! $sharedAllocation->started_at) {
                        $sharedAllocation->update(['started_at' => now()]);
                    }

                    // Record first scan time on task if not already set
                    if (! $task->first_scan_at) {
                        $task->update(['first_scan_at' => now()]);
                    }
                }

                Log::info('Selected shared box', [
                    'box_id' => $sharedBox->id,
                    'box_code' => $sharedBox->kode_kerdus,
                    'user_id' => $userId,
                    'allocation_id' => $sharedAllocation->id,
                    'completed_items' => $sharedAllocation->completed_items,
                    'allocated_items' => $sharedAllocation->allocated_items,
                ]);

                return $sharedBox;
            }

            // Third Priority: Check if user has target breakdown for this jenis (fallback)
            $targetBreakdown = \App\Models\DailyPackingTaskTarget::where('daily_packing_task_id', $task->id)
                ->where('jenis_quran_id', $jenisQuranId)
                ->where('completed_quantity', '<', DB::raw('target_quantity'))
                ->lockForUpdate()
                ->first();

            if ($targetBreakdown) {
                // Create dynamic box as fallback (legacy behavior)
                Log::info('No assigned box found, creating dynamic box as fallback', [
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'jenis_quran_id' => $jenisQuranId,
                ]);

                return $task->createDynamicBoxForJenisSafe($jenisQuranId);
            }

            // No valid assignment found
            Log::warning('No valid box assignment found', [
                'task_id' => $task->id,
                'user_id' => $userId,
                'jenis_quran_id' => $jenisQuranId,
                'assignment_method' => $task->assignment_method,
            ]);

            return null;
        });
    }

    /**
     * Parse QR data to get resi number
     */
    private function parseQRData($qrData)
    {
        // Additional sanitization
        $qrData = trim($qrData);

        // Prevent JSON bomb attacks by limiting depth and size
        if (strlen($qrData) > 1000) {
            Log::warning('Oversized QR data detected', [
                'user_id' => auth()->id(),
                'size' => strlen($qrData),
                'ip_address' => request()->ip(),
            ]);

            return null;
        }

        // Try to decode as JSON first (old format) with safe parameters
        $decoded = json_decode($qrData, true, 5, JSON_INVALID_UTF8_IGNORE);

        // Check for JSON parsing errors
        if (json_last_error() !== JSON_ERROR_NONE && json_last_error() !== JSON_ERROR_SYNTAX) {
            Log::warning('Invalid JSON in QR data', [
                'user_id' => auth()->id(),
                'json_error' => json_last_error_msg(),
                'ip_address' => request()->ip(),
            ]);

            return null;
        }

        if ($decoded && isset($decoded['no_resi'])) {
            $resi = trim($decoded['no_resi']);
            if (preg_match('/^EQ-\d{4}-\d{5}$/', $resi)) {
                return $resi;
            }
        }

        if ($decoded && isset($decoded['resi'])) {
            $resi = trim($decoded['resi']);
            if (preg_match('/^EQ-\d{4}-\d{5}$/', $resi)) {
                return $resi;
            }
        }

        // If not JSON, treat as plain resi string
        $plainResi = trim($qrData);
        if (preg_match('/^EQ-\d{4}-\d{5}$/', $plainResi)) {
            return $plainResi;
        }

        return null;
    }

    /**
     * Get concurrency metrics and system health
     */
    public function getConcurrencyMetrics()
    {
        Gate::authorize('viewWarehouseStats', auth()->user());

        return response()->json([
            'success' => true,
            'data' => $this->concurrencyMonitor->getSystemHealthCheck(),
        ]);
    }

    /**
     * Clear concurrency metrics (admin only)
     */
    public function clearConcurrencyMetrics()
    {
        Gate::authorize('manageWarehouse', auth()->user());

        $this->concurrencyMonitor->clearMetrics();

        return response()->json([
            'success' => true,
            'message' => 'Metrics cleared successfully',
        ]);
    }
}
