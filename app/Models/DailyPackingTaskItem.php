<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPackingTaskItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_packing_task_id',
        'pengiriman_id',
        'is_packed',
        'assigned_at',
        'packed_at'
    ];

    protected $casts = [
        'is_packed' => 'boolean',
        'assigned_at' => 'datetime',
        'packed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function dailyPackingTask()
    {
        return $this->belongsTo(DailyPackingTask::class);
    }

    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class);
    }

    /**
     * Scopes
     */
    public function scopePacked($query)
    {
        return $query->where('is_packed', true);
    }

    public function scopeUnpacked($query)
    {
        return $query->where('is_packed', false);
    }
}