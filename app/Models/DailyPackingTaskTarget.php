<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPackingTaskTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_packing_task_id',
        'jenis_quran_id',
        'target_boxes',
        'target_quantity',
        'completed_quantity',
        'box_capacity',
        'is_shared_box',
        'shared_box_code',
        'assignment_metadata',
    ];

    protected $casts = [
        'is_shared_box' => 'boolean',
        'assignment_metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function dailyPackingTask()
    {
        return $this->belongsTo(DailyPackingTask::class);
    }

    public function jenisQuran()
    {
        return $this->belongsTo(JenisQuran::class);
    }

    public function sharedBoxAssignments()
    {
        return $this->hasMany(SharedBoxAssignment::class, 'daily_packing_task_id', 'daily_packing_task_id')
            ->whereHas('packingBox', function ($query) {
                $query->where('jenis_quran_id', $this->jenis_quran_id);
            });
    }

    /**
     * Computed attributes
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->target_quantity - $this->completed_quantity);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_quantity === 0) {
            return 0;
        }

        return round(($this->completed_quantity / $this->target_quantity) * 100, 2);
    }

    public function getBoxesNeededAttribute(): int
    {
        if ($this->box_capacity === 0) {
            return 0;
        }

        return ceil($this->target_quantity / $this->box_capacity);
    }

    public function getBoxesCompletedAttribute(): int
    {
        if ($this->box_capacity === 0) {
            return 0;
        }

        return floor($this->completed_quantity / $this->box_capacity);
    }

    public function getCurrentBoxFillAttribute(): int
    {
        if ($this->box_capacity === 0) {
            return 0;
        }

        return $this->completed_quantity % $this->box_capacity;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->completed_quantity >= $this->target_quantity;
    }

    /**
     * Methods
     */
    public function incrementProgress(int $quantity = 1): void
    {
        $this->increment('completed_quantity', $quantity);

        // Update parent task if all targets completed
        $this->checkTaskCompletion();
    }

    public function checkTaskCompletion(): void
    {
        $task = $this->dailyPackingTask;

        // Check if all targets for this task are completed
        $allTargetsCompleted = $task->targetBreakdowns()
            ->where('completed_quantity', '>=', \DB::raw('target_quantity'))
            ->count() === $task->targetBreakdowns()->count();

        if ($allTargetsCompleted && $task->status !== DailyPackingTask::STATUS_COMPLETED) {
            $task->completeTask();
        }
    }

    public function getDetailedProgress(): array
    {
        return [
            'jenis' => $this->jenisQuran->kode_jenis,
            'target_boxes' => $this->target_boxes,
            'target_quantity' => $this->target_quantity,
            'completed_quantity' => $this->completed_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'progress_percentage' => $this->progress_percentage,
            'boxes_completed' => $this->boxes_completed,
            'current_box_fill' => $this->current_box_fill,
            'box_capacity' => $this->box_capacity,
            'is_shared' => $this->is_shared_box,
            'is_completed' => $this->is_completed,
        ];
    }

    /**
     * Scopes
     */
    public function scopeForJenis($query, $jenisId)
    {
        return $query->where('jenis_quran_id', $jenisId);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared_box', true);
    }

    public function scopeIndividual($query)
    {
        return $query->where('is_shared_box', false);
    }

    public function scopeCompleted($query)
    {
        return $query->whereColumn('completed_quantity', '>=', 'target_quantity');
    }

    public function scopeInProgress($query)
    {
        return $query->where('completed_quantity', '>', 0)
            ->whereColumn('completed_quantity', '<', 'target_quantity');
    }

    public function scopePending($query)
    {
        return $query->where('completed_quantity', 0);
    }
}
