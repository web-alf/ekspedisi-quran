<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharedBoxAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'packing_box_id',
        'daily_packing_task_id',
        'user_id',
        'allocated_items',
        'completed_items',
        'started_at',
        'completed_at',
        'progress_metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function packingBox()
    {
        return $this->belongsTo(PackingBox::class);
    }

    public function dailyPackingTask()
    {
        return $this->belongsTo(DailyPackingTask::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Computed attributes
     */
    public function getRemainingItemsAttribute(): int
    {
        return max(0, $this->allocated_items - $this->completed_items);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->allocated_items === 0) {
            return 0;
        }

        return round(($this->completed_items / $this->allocated_items) * 100, 2);
    }

    public function getIsStartedAttribute(): bool
    {
        return ! is_null($this->started_at);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->completed_items >= $this->allocated_items;
    }

    public function getCanContributeAttribute(): bool
    {
        return $this->completed_items < $this->allocated_items &&
               $this->packingBox &&
               ! $this->packingBox->is_full;
    }

    public function getContributionRateAttribute(): float
    {
        if (! $this->started_at || $this->completed_items === 0) {
            return 0;
        }

        $timeSpent = $this->completed_at
            ? $this->started_at->diffInMinutes($this->completed_at)
            : $this->started_at->diffInMinutes(now());

        if ($timeSpent === 0) {
            return 0;
        }

        return round($this->completed_items / $timeSpent, 2); // items per minute
    }

    /**
     * Methods
     */
    public function startWork(): void
    {
        if (! $this->is_started) {
            $this->update([
                'started_at' => now(),
                'progress_metadata' => array_merge($this->progress_metadata ?? [], [
                    'started_by_user' => true,
                    'start_timestamp' => now()->toISOString(),
                ]),
            ]);
        }
    }

    public function incrementProgress(int $quantity = 1): bool
    {
        if (! $this->can_contribute) {
            return false;
        }

        $wasStarted = $this->is_started;
        $previousProgress = $this->progress_percentage;

        // Start work if not started
        if (! $this->is_started) {
            $this->startWork();
            $this->notifySharedBoxStart();
        }

        // Calculate max items we can add
        $maxCanAdd = $this->remaining_items;
        $actualQuantity = min($quantity, $maxCanAdd);

        if ($actualQuantity > 0) {
            $this->increment('completed_items', $actualQuantity);
            $this->refresh();

            // Send milestone notifications
            $this->checkAndNotifyMilestones($previousProgress, $this->progress_percentage);

            // Check if completed
            if ($this->is_completed) {
                $this->markAsCompleted();
                $this->notifySharedBoxContribution();
            }

            // Update progress metadata
            $this->updateProgressMetadata($actualQuantity);

            // Notify other contributors about progress
            $this->notifyOtherContributorsProgress($actualQuantity);

            return true;
        }

        return false;
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'completed_at' => now(),
            'progress_metadata' => array_merge($this->progress_metadata ?? [], [
                'completed_by_user' => true,
                'completion_timestamp' => now()->toISOString(),
                'total_time_minutes' => $this->started_at ? $this->started_at->diffInMinutes(now()) : 0,
            ]),
        ]);

        // Create completion notification
        PackingNotification::create([
            'user_id' => $this->user_id,
            'daily_packing_task_id' => $this->daily_packing_task_id,
            'type' => 'success',
            'level' => 'info',
            'title' => 'Shared Box Allocation Completed',
            'message' => "✅ Anda telah menyelesaikan alokasi {$this->allocated_items} items di shared box {$this->packingBox->kode_kerdus}",
            'meta_data' => [
                'box_code' => $this->packingBox->kode_kerdus,
                'allocated_items' => $this->allocated_items,
                'completion_time' => now()->format('H:i'),
            ],
        ]);

        // Check if this completion triggers shared box completion workflow
        $this->checkAndTriggerBoxCompletion();
    }

    /**
     * Check if all contributors are done and trigger completion workflow
     */
    private function checkAndTriggerBoxCompletion(): void
    {
        $box = $this->packingBox;

        // Only trigger for shared boxes
        if ($box->assignment_type !== 'shared') {
            return;
        }

        // Check if box is ready for completion
        if (static::isBoxReadyForCompletion($box)) {
            // Trigger completion workflow
            $result = static::triggerCompletionWorkflow($box);

            if ($result['success']) {
                \Log::info('Shared box completion workflow triggered', [
                    'box_id' => $box->id,
                    'box_code' => $box->kode_kerdus,
                    'triggered_by' => $this->user_id,
                    'result' => $result,
                ]);
            }
        }
    }

    private function updateProgressMetadata(int $itemsAdded): void
    {
        $metadata = $this->progress_metadata ?? [];

        $metadata['last_update'] = now()->toISOString();
        $metadata['total_scans'] = ($metadata['total_scans'] ?? 0) + 1;
        $metadata['items_added_last'] = $itemsAdded;

        // Track hourly progress
        $currentHour = now()->format('H');
        if (! isset($metadata['hourly_progress'])) {
            $metadata['hourly_progress'] = [];
        }
        $metadata['hourly_progress'][$currentHour] = ($metadata['hourly_progress'][$currentHour] ?? 0) + $itemsAdded;

        $this->update(['progress_metadata' => $metadata]);
    }

    public function getDetailedProgress(): array
    {
        return [
            'user_name' => $this->user->name,
            'box_code' => $this->packingBox->kode_kerdus,
            'allocated_items' => $this->allocated_items,
            'completed_items' => $this->completed_items,
            'remaining_items' => $this->remaining_items,
            'progress_percentage' => $this->progress_percentage,
            'is_started' => $this->is_started,
            'is_completed' => $this->is_completed,
            'can_contribute' => $this->can_contribute,
            'contribution_rate' => $this->contribution_rate,
            'started_at' => $this->started_at?->format('H:i'),
            'completed_at' => $this->completed_at?->format('H:i'),
            'metadata' => $this->progress_metadata,
        ];
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBox($query, $boxId)
    {
        return $query->where('packing_box_id', $boxId);
    }

    public function scopeActive($query)
    {
        return $query->whereColumn('completed_items', '<', 'allocated_items')
            ->whereHas('packingBox', function ($q) {
                $q->where('status', '!=', 'sealed');
            });
    }

    public function scopeCompleted($query)
    {
        return $query->whereColumn('completed_items', '>=', 'allocated_items');
    }

    public function scopeInProgress($query)
    {
        return $query->where('completed_items', '>', 0)
            ->whereColumn('completed_items', '<', 'allocated_items');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereHas('dailyPackingTask', function ($q) use ($date) {
            $q->whereDate('tanggal_tugas', $date);
        });
    }

    /**
     * Static methods
     */
    public static function getTeamProgressForBox(PackingBox $box): array
    {
        $assignments = static::where('packing_box_id', $box->id)
            ->with('user')
            ->orderBy('allocated_items', 'desc')
            ->get();

        return $assignments->map(function ($assignment) {
            return $assignment->getDetailedProgress();
        })->toArray();
    }

    /**
     * Enhanced notification methods for shared box collaboration
     */
    private function notifySharedBoxStart(): void
    {
        // Get other contributors for this shared box
        $otherContributors = static::where('packing_box_id', $this->packing_box_id)
            ->where('user_id', '!=', $this->user_id)
            ->with('user:id,name')
            ->get();

        foreach ($otherContributors as $contributor) {
            PackingNotification::create([
                'user_id' => $contributor->user_id,
                'daily_packing_task_id' => $this->daily_packing_task_id,
                'type' => 'collaboration',
                'level' => 'info',
                'title' => 'Shared Box - Team Member Started',
                'message' => "🤝 {$this->user->name} mulai bekerja di shared box {$this->packingBox->kode_kerdus}",
                'meta_data' => [
                    'box_code' => $this->packingBox->kode_kerdus,
                    'started_by' => $this->user->name,
                    'started_at' => now()->format('H:i'),
                    'collaboration_type' => 'shared_box_start',
                ],
            ]);
        }
    }

    private function checkAndNotifyMilestones(float $previousProgress, float $currentProgress): void
    {
        $milestones = [25, 50, 75];

        foreach ($milestones as $milestone) {
            if ($previousProgress < $milestone && $currentProgress >= $milestone) {
                // Notify self
                PackingNotification::create([
                    'user_id' => $this->user_id,
                    'daily_packing_task_id' => $this->daily_packing_task_id,
                    'type' => 'milestone',
                    'level' => 'success',
                    'title' => 'Shared Box Progress Milestone',
                    'message' => "🎯 Milestone {$milestone}% tercapai di shared box {$this->packingBox->kode_kerdus}!",
                    'meta_data' => [
                        'box_code' => $this->packingBox->kode_kerdus,
                        'milestone' => $milestone,
                        'completed_items' => $this->completed_items,
                        'allocated_items' => $this->allocated_items,
                    ],
                ]);

                // Notify other contributors
                $this->notifyOtherContributorsMilestone($milestone);
            }
        }
    }

    private function notifyOtherContributorsMilestone(int $milestone): void
    {
        $otherContributors = static::where('packing_box_id', $this->packing_box_id)
            ->where('user_id', '!=', $this->user_id)
            ->with('user:id,name')
            ->get();

        foreach ($otherContributors as $contributor) {
            PackingNotification::create([
                'user_id' => $contributor->user_id,
                'daily_packing_task_id' => $this->daily_packing_task_id,
                'type' => 'collaboration',
                'level' => 'info',
                'title' => 'Team Progress Update',
                'message' => "📈 {$this->user->name} mencapai {$milestone}% di shared box {$this->packingBox->kode_kerdus}",
                'meta_data' => [
                    'box_code' => $this->packingBox->kode_kerdus,
                    'milestone_by' => $this->user->name,
                    'milestone' => $milestone,
                    'collaboration_type' => 'milestone_reached',
                ],
            ]);
        }
    }

    private function notifyOtherContributorsProgress(int $itemsAdded): void
    {
        // Only notify on significant progress (every 5 items or more)
        if ($itemsAdded < 5) {
            return;
        }

        $otherContributors = static::where('packing_box_id', $this->packing_box_id)
            ->where('user_id', '!=', $this->user_id)
            ->where('completed_items', '<', DB::raw('allocated_items')) // Still active
            ->with('user:id,name')
            ->get();

        foreach ($otherContributors as $contributor) {
            PackingNotification::create([
                'user_id' => $contributor->user_id,
                'daily_packing_task_id' => $this->daily_packing_task_id,
                'type' => 'collaboration',
                'level' => 'info',
                'title' => 'Shared Box Update',
                'message' => "📦 {$this->user->name} menambah {$itemsAdded} items ke shared box {$this->packingBox->kode_kerdus}",
                'meta_data' => [
                    'box_code' => $this->packingBox->kode_kerdus,
                    'items_added' => $itemsAdded,
                    'added_by' => $this->user->name,
                    'total_progress' => $this->progress_percentage,
                    'collaboration_type' => 'progress_update',
                ],
            ]);
        }
    }

    private function notifySharedBoxContribution(): void
    {
        // Check if shared box is now complete by all contributors
        $boxProgress = static::getTotalProgressForBox($this->packingBox);

        if ($boxProgress['completed_contributors'] === $boxProgress['contributors_count']) {
            // Box fully completed - notify all contributors
            $allContributors = static::where('packing_box_id', $this->packing_box_id)
                ->with('user:id,name')
                ->get();

            foreach ($allContributors as $contributor) {
                PackingNotification::create([
                    'user_id' => $contributor->user_id,
                    'daily_packing_task_id' => $this->daily_packing_task_id,
                    'type' => 'success',
                    'level' => 'success',
                    'title' => 'Shared Box Completed!',
                    'message' => "🎉 Shared box {$this->packingBox->kode_kerdus} telah selesai oleh semua tim!",
                    'meta_data' => [
                        'box_code' => $this->packingBox->kode_kerdus,
                        'total_items' => $boxProgress['total_allocated'],
                        'contributors' => $allContributors->pluck('user.name')->toArray(),
                        'completion_time' => now()->format('H:i'),
                        'collaboration_type' => 'shared_box_completed',
                    ],
                ]);
            }
        } else {
            // Just this contributor completed - notify others
            $otherContributors = static::where('packing_box_id', $this->packing_box_id)
                ->where('user_id', '!=', $this->user_id)
                ->with('user:id,name')
                ->get();

            foreach ($otherContributors as $contributor) {
                PackingNotification::create([
                    'user_id' => $contributor->user_id,
                    'daily_packing_task_id' => $this->daily_packing_task_id,
                    'type' => 'collaboration',
                    'level' => 'success',
                    'title' => 'Team Member Completed',
                    'message' => "✅ {$this->user->name} telah menyelesaikan bagiannya di shared box {$this->packingBox->kode_kerdus}",
                    'meta_data' => [
                        'box_code' => $this->packingBox->kode_kerdus,
                        'completed_by' => $this->user->name,
                        'remaining_contributors' => $boxProgress['contributors_count'] - $boxProgress['completed_contributors'],
                        'collaboration_type' => 'contributor_completed',
                    ],
                ]);
            }
        }
    }

    public static function getTotalProgressForBox(PackingBox $box): array
    {
        $assignments = static::where('packing_box_id', $box->id)->get();

        $totalAllocated = $assignments->sum('allocated_items');
        $totalCompleted = $assignments->sum('completed_items');

        return [
            'total_allocated' => $totalAllocated,
            'total_completed' => $totalCompleted,
            'total_remaining' => $totalAllocated - $totalCompleted,
            'overall_progress' => $totalAllocated > 0 ? round(($totalCompleted / $totalAllocated) * 100, 2) : 0,
            'contributors_count' => $assignments->count(),
            'active_contributors' => $assignments->where('is_started', true)->where('is_completed', false)->count(),
            'completed_contributors' => $assignments->where('is_completed', true)->count(),
            'is_ready_for_completion' => static::isBoxReadyForCompletion($box, $assignments),
            'completion_status' => static::getBoxCompletionStatus($box, $assignments),
        ];
    }

    /**
     * Check if shared box is ready for completion workflow
     */
    public static function isBoxReadyForCompletion(PackingBox $box, $assignments = null): bool
    {
        if (! $assignments) {
            $assignments = static::where('packing_box_id', $box->id)->get();
        }

        // All contributors must complete their allocations
        $allCompleted = $assignments->every(function ($assignment) {
            return $assignment->is_completed;
        });

        // Box must have capacity remaining for sealing
        $totalCompleted = $assignments->sum('completed_items');
        $canSeal = $totalCompleted >= $box->kapasitas * 0.95; // 95% capacity threshold

        return $allCompleted && $canSeal;
    }

    /**
     * Get detailed completion status for shared box
     */
    public static function getBoxCompletionStatus(PackingBox $box, $assignments = null): array
    {
        if (! $assignments) {
            $assignments = static::where('packing_box_id', $box->id)->get();
        }

        $totalCompleted = $assignments->sum('completed_items');
        $completedContributors = $assignments->where('is_completed', true)->count();
        $totalContributors = $assignments->count();

        // Calculate completion phases
        $phase = 'in_progress';
        $readyToSeal = false;
        $message = '';

        if ($completedContributors === 0) {
            $phase = 'not_started';
            $message = 'Belum ada contributor yang mulai';
        } elseif ($completedContributors === $totalContributors) {
            if ($totalCompleted >= $box->kapasitas * 0.95) {
                $phase = 'ready_to_seal';
                $readyToSeal = true;
                $message = 'Semua contributor selesai, siap untuk seal';
            } else {
                $phase = 'completed_insufficient';
                $message = 'Semua contributor selesai, namun belum mencapai kapasitas minimum';
            }
        } else {
            $remainingContributors = $totalContributors - $completedContributors;
            $phase = 'partial_completion';
            $message = "Menunggu {$remainingContributors} contributor lainnya";
        }

        return [
            'phase' => $phase,
            'ready_to_seal' => $readyToSeal,
            'message' => $message,
            'completed_contributors' => $completedContributors,
            'total_contributors' => $totalContributors,
            'completion_percentage' => $totalContributors > 0 ? round(($completedContributors / $totalContributors) * 100, 2) : 0,
            'capacity_filled' => $box->kapasitas > 0 ? round(($totalCompleted / $box->kapasitas) * 100, 2) : 0,
            'can_proceed_to_seal' => $readyToSeal && $box->status === 'filling',
        ];
    }

    /**
     * Trigger completion workflow when all contributors are done
     */
    public static function triggerCompletionWorkflow(PackingBox $box): array
    {
        $assignments = static::where('packing_box_id', $box->id)->get();
        $completionStatus = static::getBoxCompletionStatus($box, $assignments);

        if ($completionStatus['ready_to_seal']) {
            // Update box status to ready for sealing
            $box->update([
                'status' => 'ready_to_seal',
                'completion_triggered_at' => now(),
            ]);

            // Create completion notifications for all contributors
            foreach ($assignments as $assignment) {
                PackingNotification::create([
                    'user_id' => $assignment->user_id,
                    'daily_packing_task_id' => $assignment->daily_packing_task_id,
                    'type' => 'completion_workflow',
                    'level' => 'success',
                    'title' => 'Shared Box - Siap untuk Seal!',
                    'message' => "🎉 Shared box {$box->kode_kerdus} telah selesai oleh semua tim dan siap untuk di-seal!",
                    'meta_data' => [
                        'box_code' => $box->kode_kerdus,
                        'total_items' => $assignments->sum('completed_items'),
                        'contributors' => $assignments->pluck('user.name')->toArray(),
                        'completion_time' => now()->format('H:i'),
                        'workflow_phase' => 'ready_to_seal',
                        'action_required' => 'seal_box',
                    ],
                ]);
            }

            // Notify supervisors
            static::notifySupervitorsBoxCompletion($box, $assignments);

            return [
                'success' => true,
                'message' => 'Shared box completion workflow triggered',
                'next_action' => 'seal_box',
                'completion_status' => $completionStatus,
            ];
        }

        return [
            'success' => false,
            'message' => 'Box not ready for completion workflow',
            'completion_status' => $completionStatus,
        ];
    }

    /**
     * Notify supervisors about shared box completion
     */
    private static function notifySupervitorsBoxCompletion(PackingBox $box, $assignments): void
    {
        $supervisors = \App\Models\User::permission('supervisor.warehouse.monitor')->get();

        foreach ($supervisors as $supervisor) {
            PackingNotification::create([
                'user_id' => $supervisor->id,
                'daily_packing_task_id' => $assignments->first()->daily_packing_task_id,
                'type' => 'supervisor_alert',
                'level' => 'info',
                'title' => 'Shared Box Ready for Sealing',
                'message' => "📦 Shared box {$box->kode_kerdus} telah selesai oleh semua contributor dan memerlukan sealing.",
                'meta_data' => [
                    'box_code' => $box->kode_kerdus,
                    'total_items' => $assignments->sum('completed_items'),
                    'contributors' => $assignments->pluck('user.name')->toArray(),
                    'completion_time' => now()->format('H:i'),
                    'requires_action' => 'seal_approval',
                ],
            ]);
        }
    }
}
