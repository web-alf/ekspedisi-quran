<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobProgress extends Model
{
    use HasFactory;

    protected $table = 'job_progress';

    protected $fillable = [
        'job_id',
        'job_type',
        'title',
        'user_id',
        'status',
        'total_items',
        'processed_items',
        'failed_items',
        'progress_percentage',
        'metadata',
        'results',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'results' => 'array',
        'progress_percentage' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('job_type', $type);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Accessors
     */
    public function getStatusDisplayAttribute()
    {
        $statuses = [
            'pending' => 'Menunggu',
            'processing' => 'Diproses',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    public function getDurationAttribute()
    {
        if (! $this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();

        return $this->started_at->diffInSeconds($endTime);
    }

    public function getDurationFormattedAttribute()
    {
        $duration = $this->duration;
        if (! $duration) {
            return null;
        }

        if ($duration < 60) {
            return $duration.' detik';
        } elseif ($duration < 3600) {
            return round($duration / 60, 1).' menit';
        } else {
            return round($duration / 3600, 1).' jam';
        }
    }

    public function getSuccessRateAttribute()
    {
        if ($this->total_items === 0) {
            return 0;
        }

        $successful = $this->processed_items - $this->failed_items;

        return round(($successful / $this->total_items) * 100, 2);
    }

    /**
     * Job progress management methods
     */
    public function updateProgress($processedItems, $failedItems = 0)
    {
        $this->processed_items = $processedItems;
        $this->failed_items = $failedItems;

        if ($this->total_items > 0) {
            $this->progress_percentage = round(($processedItems / $this->total_items) * 100, 2);
        }

        $this->save();

        return $this;
    }

    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        return $this;
    }

    public function markAsCompleted(array $results = [])
    {
        $this->update([
            'status' => 'completed',
            'progress_percentage' => 100.00,
            'results' => $results,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function markAsFailed(?string $errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Static helper methods
     */
    public static function createForJob($jobId, $jobType, $title, $totalItems, $metadata = [])
    {
        return static::create([
            'job_id' => $jobId,
            'job_type' => $jobType,
            'title' => $title,
            'user_id' => auth()->id(),
            'total_items' => $totalItems,
            'metadata' => $metadata,
        ]);
    }

    public static function getActiveJobsForUser($userId)
    {
        return static::active()
            ->byUser($userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getRecentJobsForUser($userId, $limit = 10)
    {
        return static::byUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getStats(?int $userId = null)
    {
        $query = static::recent();

        if ($userId) {
            $query->byUser($userId);
        }

        $jobs = $query->get();

        return [
            'total_jobs' => $jobs->count(),
            'completed' => $jobs->where('status', 'completed')->count(),
            'failed' => $jobs->where('status', 'failed')->count(),
            'active' => $jobs->whereIn('status', ['pending', 'processing'])->count(),
            'total_items_processed' => $jobs->sum('processed_items'),
            'total_items_failed' => $jobs->sum('failed_items'),
            'avg_success_rate' => $jobs->where('status', 'completed')->avg('success_rate'),
            'avg_duration' => $jobs->where('status', 'completed')->avg('duration'),
        ];
    }
}
