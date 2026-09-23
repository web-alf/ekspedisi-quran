<?php

namespace App\Services;

use App\Models\DailyPackingTask;
use App\Models\DailyPackingTaskItem;
use App\Models\PackingNotification;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackingAssignmentService
{
    /**
     * Default daily target
     * @deprecated Use config('packing.default_daily_target') instead
     */
    const DEFAULT_DAILY_TARGET = 80;

    /**
     * Run daily assignment for all warehouse users
     * DISABLED: Automatic assignment removed - supervisor manual assignment only
     */
    public function runDailyAssignment($date = null)
    {
        $date = $date ? Carbon::parse($date) : today();

        // Skip weekends if configured
        if ($this->shouldSkipDate($date)) {
            Log::info('Skipping daily assignment for date: '.$date->format('Y-m-d'));

            return;
        }

        DB::beginTransaction();

        try {
            // Get all active warehouse users
            $warehouseUsers = User::permission('warehouse.dashboard')
                ->where('is_active', true)
                ->get();

            $results = [];

            foreach ($warehouseUsers as $user) {
                $result = $this->assignDailyTaskToUser($user, $date);
                $results[] = $result;
            }

            DB::commit();

            Log::info('Daily assignment completed', [
                'date' => $date->format('Y-m-d'),
                'users_assigned' => count($results),
                'total_resi_assigned' => collect($results)->sum('resi_count'),
            ]);

            return $results;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Daily assignment failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign daily task to specific user (TARGET-ONLY SYSTEM)
     * Only creates target without specific pengiriman assignment
     */
    public function assignDailyTaskToUser(User $user, $date = null)
    {
        return DB::transaction(function () use ($user, $date) {
            $date = $date ? Carbon::parse($date) : today();

            // Check if task already exists (with lock - race-safe)
            $existingTask = DailyPackingTask::where('user_id', $user->id)
                ->whereDate('tanggal_tugas', $date)
                ->lockForUpdate()
                ->first();

            if ($existingTask) {
                Log::info('Task already exists for user', [
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                ]);

                return [
                    'user_id' => $user->id,
                    'task_id' => $existingTask->id,
                    'status' => 'existing',
                    'target' => $existingTask->total_target,
                ];
            }

            // Calculate carry over from yesterday
            $carryOver = $this->calculateCarryOver($user, $date);

            // Calculate today's target
            $dailyTarget = config('packing.default_daily_target', self::DEFAULT_DAILY_TARGET) + $carryOver;

            // Create new task with TARGET ONLY (no specific assignments)
            $task = DailyPackingTask::create([
                'user_id' => $user->id,
                'tanggal_tugas' => $date,
                'total_target' => $dailyTarget,
                'sisa_kemarin' => $carryOver,
                'status' => DailyPackingTask::STATUS_ASSIGNED,
                'assigned_at' => now(),
            ]);

            // Generate packing boxes for different jenis
            $task->generateJenisBasedPackingBoxes();

            // Send notification
            $this->sendAssignmentNotification($task);

            Log::info('Daily task assigned (target-only)', [
                'user_id' => $user->id,
                'task_id' => $task->id,
                'target' => $dailyTarget,
                'carry_over' => $carryOver,
                'assignment_type' => 'target_only',
            ]);

            return [
                'user_id' => $user->id,
                'task_id' => $task->id,
                'status' => 'new',
                'target' => $dailyTarget,
                'carry_over' => $carryOver,
            ];
        }, 3); // Retry up to 3 times for deadlock
    }

    /**
     * Assign daily task to user with custom target (TARGET-ONLY MANUAL ASSIGNMENT)
     */
    public function assignDailyTaskToUserWithTarget(User $user, $date = null, $customTarget = null, $allowUpdate = false)
    {
        return DB::transaction(function () use ($user, $date, $customTarget, $allowUpdate) {
            $date = $date ? Carbon::parse($date) : today();
            $customTarget = $customTarget ?: config('packing.default_daily_target', self::DEFAULT_DAILY_TARGET);

            // Check if task already exists (with lock)
            $existingTask = DailyPackingTask::where('user_id', $user->id)
                ->whereDate('tanggal_tugas', $date)
                ->lockForUpdate()
                ->first();

            if ($existingTask) {
                // If allowUpdate is true, update the existing task instead of throwing error
                if ($allowUpdate) {
                    return $this->updateTaskTarget($existingTask, $customTarget);
                }

                Log::info('Task already exists for user', [
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                ]);
                throw new \Exception('Task sudah ada untuk user ini pada tanggal tersebut');
            }

            // Calculate carry over from yesterday
            $carryOver = $this->calculateCarryOver($user, $date);

            // Use custom target plus carry over
            $totalTarget = $customTarget + $carryOver;

            // Create new task with TARGET ONLY (no specific assignments)
            $task = DailyPackingTask::create([
                'user_id' => $user->id,
                'tanggal_tugas' => $date,
                'total_target' => $totalTarget,
                'sisa_kemarin' => $carryOver,
                'status' => DailyPackingTask::STATUS_ASSIGNED,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(), // Track who assigned manually
            ]);

            // HYBRID DYNAMIC: No pre-generated boxes, create on-demand when scanning

            // Send notification
            $this->sendAssignmentNotification($task, true); // Mark as manual assignment

            Log::info('Manual task assigned (target-only)', [
                'user_id' => $user->id,
                'task_id' => $task->id,
                'custom_target' => $customTarget,
                'total_target' => $totalTarget,
                'carry_over' => $carryOver,
                'assigned_by' => auth()->id(),
                'assignment_type' => 'target_only',
            ]);

            return [
                'user_id' => $user->id,
                'task_id' => $task->id,
                'status' => 'manual_assigned',
                'custom_target' => $customTarget,
                'total_target' => $totalTarget,
                'carry_over' => $carryOver,
            ];
        }, 3); // Retry up to 3 times for deadlock
    }

    /**
     * Update target for existing task
     */
    public function updateTaskTarget(DailyPackingTask $task, $newTarget)
    {
        // Prevent update if task is already completed
        if ($task->status === DailyPackingTask::STATUS_COMPLETED) {
            throw new \Exception('Task sudah selesai, tidak dapat diubah targetnya');
        }

        // Ensure new target is not less than already completed
        if ($newTarget < $task->total_selesai) {
            throw new \Exception('Target baru tidak boleh lebih kecil dari yang sudah diselesaikan ('.$task->total_selesai.' mushaf)');
        }

        $oldTarget = $task->total_target;
        $carryOver = $task->sisa_kemarin ?? 0;

        // Calculate new total target (new target + carry over)
        $newTotalTarget = $newTarget + $carryOver;

        // Update task
        $task->update([
            'total_target' => $newTotalTarget,
            'assigned_by' => auth()->id(), // Track who updated
        ]);

        Log::info('Task target updated', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'old_target' => $oldTarget,
            'new_target' => $newTotalTarget,
            'new_base_target' => $newTarget,
            'carry_over' => $carryOver,
            'updated_by' => auth()->id(),
        ]);

        // Send notification about target change
        PackingNotification::create([
            'user_id' => $task->user_id,
            'daily_packing_task_id' => $task->id,
            'type' => 'alert',
            'level' => 'warning',
            'title' => 'Target Diperbarui',
            'message' => "Target Anda telah diperbarui dari {$oldTarget} menjadi {$newTotalTarget} mushaf.",
            'meta_data' => [
                'old_target' => $oldTarget,
                'new_target' => $newTotalTarget,
                'updated_by' => auth()->id(),
            ],
        ]);

        return [
            'user_id' => $task->user_id,
            'task_id' => $task->id,
            'status' => 'target_updated',
            'old_target' => $oldTarget,
            'new_target' => $newTotalTarget,
            'new_base_target' => $newTarget,
            'carry_over' => $carryOver,
        ];
    }

    /**
     * Calculate carry over from previous days
     */
    private function calculateCarryOver(User $user, Carbon $date)
    {
        $yesterday = $date->copy()->subDay();

        $yesterdayTask = DailyPackingTask::where('user_id', $user->id)
            ->whereDate('tanggal_tugas', $yesterday)
            ->first();

        if (! $yesterdayTask) {
            return 0;
        }

        // If yesterday's task is not completed
        if (! $yesterdayTask->is_completed) {
            return $yesterdayTask->remaining;
        }

        return 0;
    }

    /**
     * DEPRECATED: No longer used in target-only system
     * Items are assigned dynamically when scanned
     */
    private function assignPengirimanToTask(DailyPackingTask $task)
    {
        // In target-only system, we don't pre-assign pengiriman
        // Items will be assigned when staff scans QR codes
        Log::info('assignPengirimanToTask called but skipped in target-only system', [
            'task_id' => $task->id,
        ]);

        return 0; // No items pre-assigned
    }

    /**
     * Send assignment notification
     */
    private function sendAssignmentNotification(DailyPackingTask $task, $isManual = false)
    {
        $title = $isManual ? 'Tugas Manual Tersedia' : 'Tugas Harian Tersedia';
        $message = $isManual
            ? "Supervisor telah memberikan tugas manual: {$task->total_target} mushaf. ".
              ($task->sisa_kemarin > 0 ? "Termasuk {$task->sisa_kemarin} mushaf dari kemarin." : '')
            : "Tugas packing hari ini: {$task->total_target} mushaf. ".
              ($task->sisa_kemarin > 0 ? "Termasuk {$task->sisa_kemarin} mushaf dari kemarin." : '');

        PackingNotification::create([
            'user_id' => $task->user_id,
            'daily_packing_task_id' => $task->id,
            'type' => $isManual ? 'alert' : 'reminder',
            'level' => $isManual ? 'warning' : 'info',
            'title' => $title,
            'message' => $message,
            'meta_data' => [
                'total_target' => $task->total_target,
                'sisa_kemarin' => $task->sisa_kemarin,
                'is_manual' => $isManual,
                'assigned_by' => $isManual ? auth()->id() : null,
            ],
        ]);
    }

    /**
     * Check if should skip date (weekends/holidays)
     */
    private function shouldSkipDate(Carbon $date)
    {
        // Skip weekends
        if ($date->isWeekend()) {
            return true;
        }

        // Check for Indonesian public holidays
        if ($this->isIndonesianPublicHoliday($date)) {
            return true;
        }

        return false;
    }

    /**
     * Check if date is Indonesian public holiday
     */
    private function isIndonesianPublicHoliday(Carbon $date)
    {
        // Common Indonesian public holidays (fixed dates) dari config
        $fixedHolidays = config('packing.fixed_holidays', [
            '01-01', // New Year's Day
            '08-17', // Independence Day
            '12-25', // Christmas Day
        ]);

        $monthDay = $date->format('m-d');
        if (in_array($monthDay, $fixedHolidays)) {
            return true;
        }

        // For lunar and religious holidays, you could use an API or database
        // Example: Check against a holidays table in database
        try {
            $holidayExists = DB::table('holidays')
                ->whereDate('holiday_date', $date->format('Y-m-d'))
                ->exists();

            if ($holidayExists) {
                return true;
            }
        } catch (\Exception $e) {
            // If holidays table doesn't exist, continue with basic checking
            Log::debug('Holiday table check failed: '.$e->getMessage());
        }

        return false;
    }

    /**
     * Assign pengiriman to user task when scanned (FREE-PICK SYSTEM)
     * Called when staff scans QR code
     */
    public function assignPengirimanOnScan(User $user, Pengiriman $pengiriman, $date = null)
    {
        $date = $date ? Carbon::parse($date) : today();

        // Get user's task for today
        $task = DailyPackingTask::where('user_id', $user->id)
            ->whereDate('tanggal_tugas', $date)
            ->first();

        if (! $task) {
            throw new \Exception('Tidak ada tugas packing untuk hari ini. Silakan hubungi supervisor.');
        }

        // Check if pengiriman is available for packing
        $packingStatusId = StatusPengirimanCache::getIdBySlug('packing');
        if ($pengiriman->status_id !== $packingStatusId) {
            throw new \Exception('Pengiriman ini tidak dalam status packing.');
        }

        // Check if already assigned (ANY date - unique constraint pengiriman_id is global)
        // Jangan filter by tanggal: unique constraint `daily_packing_task_items.pengiriman_id`
        // berlaku global, jadi pengiriman yang di-assign kemarin pun tidak boleh di-assign lagi.
        $existingAssignment = DailyPackingTaskItem::where('pengiriman_id', $pengiriman->id)
            ->with('dailyPackingTask.user')
            ->first();

        if ($existingAssignment) {
            $assignedUser = $existingAssignment->dailyPackingTask->user;

            // If already assigned to same user, return existing assignment
            if ($assignedUser->id === $user->id) {
                Log::info('Pengiriman already assigned to same user, returning existing assignment', [
                    'user_id' => $user->id,
                    'pengiriman_id' => $pengiriman->id,
                    'existing_assignment_id' => $existingAssignment->id,
                ]);

                return $existingAssignment;
            }

            // If assigned to different user, throw error
            throw new \Exception("Pengiriman ini sudah di-assign ke {$assignedUser->name} hari ini.");
        }

        // Check if user has reached their target
        if ($task->total_selesai >= $task->total_target) {
            throw new \Exception('Anda sudah mencapai target hari ini.');
        }

        // Create assignment
        $taskItem = DailyPackingTaskItem::create([
            'daily_packing_task_id' => $task->id,
            'pengiriman_id' => $pengiriman->id,
            'assigned_at' => now(),
        ]);

        // Start task if not started
        if ($task->status === DailyPackingTask::STATUS_ASSIGNED) {
            $task->startTask();
        }

        Log::info('Pengiriman assigned on scan', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'pengiriman_id' => $pengiriman->id,
            'no_resi' => $pengiriman->no_resi,
            'assignment_type' => 'free_pick',
        ]);

        return $taskItem;
    }

    /**
     * Get available pengiriman count for free-pick
     */
    public function getAvailablePengirimanCount()
    {
        $packingStatusId = StatusPengirimanCache::getIdBySlug('packing');

        return Pengiriman::where('status_id', $packingStatusId)
            ->whereNotNull('qr_code_path')
            ->whereDoesntHave('dailyPackingTaskItem', function ($q) {
                $q->whereHas('dailyPackingTask', function ($q2) {
                    $q2->whereDate('tanggal_tugas', today());
                });
            })
            ->count();
    }

    /**
     * Get available pengiriman for free-pick (paginated)
     */
    public function getAvailablePengiriman($limit = 50)
    {
        $packingStatusId = StatusPengirimanCache::getIdBySlug('packing');

        return Pengiriman::where('status_id', $packingStatusId)
            ->whereNotNull('qr_code_path')
            ->whereDoesntHave('dailyPackingTaskItem', function ($q) {
                $q->whereHas('dailyPackingTask', function ($q2) {
                    $q2->whereDate('tanggal_tugas', today());
                });
            })
            ->with(['donatur:id,nama_donatur', 'jenisQuran:id,nama_jenis', 'wakafItem:id,wakif_name'])
            ->orderBy('no_resi', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Redistribute tasks from underperforming users
     */
    public function redistributeTasks(DailyPackingTask $sourceTask, array $targetUserIds = [])
    {
        DB::beginTransaction();

        try {
            // Get unfinished items
            $unfinishedItems = $sourceTask->taskItems()
                ->where('is_packed', false)
                ->get();

            if ($unfinishedItems->isEmpty()) {
                return ['redistributed' => 0, 'message' => 'No items to redistribute'];
            }

            // If no target users specified, find users who completed their tasks
            if (empty($targetUserIds)) {
                $targetUserIds = DailyPackingTask::today()
                    ->where('status', DailyPackingTask::STATUS_COMPLETED)
                    ->where('user_id', '!=', $sourceTask->user_id)
                    ->pluck('user_id')
                    ->toArray();
            }

            if (empty($targetUserIds)) {
                return ['redistributed' => 0, 'message' => 'No available users for redistribution'];
            }

            // Distribute items evenly
            $itemsPerUser = ceil($unfinishedItems->count() / count($targetUserIds));
            $redistributed = 0;

            foreach ($targetUserIds as $index => $userId) {
                $targetTask = DailyPackingTask::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'tanggal_tugas' => today(),
                    ],
                    [
                        'total_target' => 0,
                        'status' => DailyPackingTask::STATUS_ASSIGNED,
                        'assigned_at' => now(),
                    ]
                );

                // Take items for this user
                $itemsToMove = $unfinishedItems->slice($index * $itemsPerUser, $itemsPerUser);

                foreach ($itemsToMove as $item) {
                    // Move item to new task
                    $item->update(['daily_packing_task_id' => $targetTask->id]);
                    $redistributed++;
                }

                // Update target task total
                $targetTask->increment('total_target', $itemsToMove->count());

                // Send notification
                PackingNotification::create([
                    'user_id' => $userId,
                    'daily_packing_task_id' => $targetTask->id,
                    'type' => 'alert',
                    'level' => 'warning',
                    'title' => 'Tugas Tambahan',
                    'message' => "Anda mendapat tambahan {$itemsToMove->count()} mushaf untuk dipacking.",
                    'meta_data' => [
                        'additional_items' => $itemsToMove->count(),
                        'source_user_id' => $sourceTask->user_id,
                    ],
                ]);
            }

            // Update source task
            $sourceTask->decrement('total_target', $redistributed);
            $sourceTask->update([
                'notes' => "Redistributed {$redistributed} items at ".now()->format('H:i'),
            ]);

            DB::commit();

            return [
                'redistributed' => $redistributed,
                'target_users' => count($targetUserIds),
                'message' => "Successfully redistributed {$redistributed} items",
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Redistribution failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Process daily expiration and carry over
     */
    public function processDailyExpiration()
    {
        $yesterday = today()->subDay();

        // Get all active tasks from yesterday
        $yesterdayTasks = DailyPackingTask::whereDate('tanggal_tugas', $yesterday)
            ->whereIn('status', [DailyPackingTask::STATUS_ASSIGNED, DailyPackingTask::STATUS_IN_PROGRESS])
            ->get();

        foreach ($yesterdayTasks as $task) {
            // Expire the task
            $task->expireTask();

            // Calculate performance impact
            if ($task->total_selesai < $task->total_target) {
                $achievement = $task->progress_percentage;

                // Send warning notification if achievement is very low
                if ($achievement < 50) {
                    PackingNotification::create([
                        'user_id' => $task->user_id,
                        'daily_packing_task_id' => $task->id,
                        'type' => 'warning',
                        'level' => 'critical',
                        'title' => 'Target Tidak Tercapai',
                        'message' => "Anda hanya menyelesaikan {$achievement}% dari target kemarin. Sisa {$task->remaining} mushaf akan ditambahkan ke tugas hari ini.",
                        'meta_data' => [
                            'achievement_rate' => $achievement,
                            'remaining' => $task->remaining,
                        ],
                    ]);
                }
            }
        }

        Log::info('Daily expiration processed', [
            'date' => $yesterday->format('Y-m-d'),
            'tasks_expired' => $yesterdayTasks->count(),
        ]);
    }
}
