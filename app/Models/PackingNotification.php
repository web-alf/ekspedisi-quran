<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'daily_packing_task_id',
        'type',
        'level',
        'title',
        'message',
        'is_read',
        'read_at',
        'meta_data'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'meta_data' => 'array',
    ];

    /**
     * Type constants
     */
    const TYPE_REMINDER = 'reminder';
    const TYPE_WARNING = 'warning';
    const TYPE_ALERT = 'alert';
    const TYPE_COMPLETION = 'completion';

    /**
     * Level constants
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dailyPackingTask()
    {
        return $this->belongsTo(DailyPackingTask::class);
    }

    /**
     * Scopes
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeCritical($query)
    {
        return $query->where('level', self::LEVEL_CRITICAL);
    }

    /**
     * Methods
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Accessors
     */
    public function getLevelBadgeAttribute()
    {
        $badges = [
            self::LEVEL_INFO => ['text' => 'Info', 'class' => 'bg-blue-100 text-blue-800'],
            self::LEVEL_WARNING => ['text' => 'Warning', 'class' => 'bg-yellow-100 text-yellow-800'],
            self::LEVEL_CRITICAL => ['text' => 'Critical', 'class' => 'bg-red-100 text-red-800'],
        ];

        return $badges[$this->level] ?? $badges[self::LEVEL_INFO];
    }

    public function getIconAttribute()
    {
        $icons = [
            self::TYPE_REMINDER => '⏰',
            self::TYPE_WARNING => '⚠️',
            self::TYPE_ALERT => '🚨',
            self::TYPE_COMPLETION => '✅',
        ];

        return $icons[$this->type] ?? '📢';
    }
}