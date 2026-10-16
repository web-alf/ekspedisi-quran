<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;

    protected $table = 'import_logs';

    protected $fillable = [
        'user_id',
        'filename',
        'total_rows',
        'success_rows',
        'failed_rows',
        'error_details',
        'file_path',
        'status',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'success_rows' => 'integer',
        'failed_rows' => 'integer',
        'error_details' => 'array',
    ];

    /**
     * Relationship: Import Log belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRateAttribute()
    {
        if ($this->total_rows == 0) {
            return 0;
        }

        return round(($this->success_rows / $this->total_rows) * 100, 2);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'processing' => 'blue',
            'completed' => 'green',
            'completed_with_errors' => 'yellow',
            'failed' => 'red',
            default => 'gray'
        };
    }

    /**
     * Check if import has errors
     */
    public function hasErrors()
    {
        return $this->failed_rows > 0 || $this->status === 'failed';
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted()
    {
        $status = $this->failed_rows > 0 ? 'completed_with_errors' : 'completed';
        $this->update(['status' => $status]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(mixed $errorDetails = null)
    {
        $this->update([
            'status' => 'failed',
            'error_details' => $errorDetails,
        ]);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Latest first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope: With errors only
     */
    public function scopeWithErrors($query)
    {
        return $query->where('failed_rows', '>', 0)
            ->orWhere('status', 'failed');
    }
}
