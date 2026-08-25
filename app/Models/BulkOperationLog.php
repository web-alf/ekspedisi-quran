<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BulkOperationLog extends Model
{
    use HasFactory;

    protected $table = 'bulk_operations_log';

    protected $fillable = [
        'operation_type',
        'box_code',
        'box_seal_code',
        'user_id',
        'items_count',
        'pengiriman_ids',
        'old_status_id',
        'new_status_id',
        'old_address',
        'new_address',
        'notes',
        'qr_data',
        'ip_address',
        'user_agent',
        'operation_timestamp',
        'processing_time_ms'
    ];

    protected $casts = [
        'pengiriman_ids' => 'array',
        'qr_data' => 'array',
        'operation_timestamp' => 'datetime'
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function oldStatus()
    {
        return $this->belongsTo(StatusPengiriman::class, 'old_status_id');
    }

    public function newStatus()
    {
        return $this->belongsTo(StatusPengiriman::class, 'new_status_id');
    }

    public function box()
    {
        return $this->belongsTo(PackingBox::class, 'box_code', 'kode_kerdus');
    }

    /**
     * Scopes
     */
    public function scopeToday($query)
    {
        return $query->whereDate('operation_timestamp', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('operation_timestamp', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('operation_timestamp', now()->month)
                    ->whereYear('operation_timestamp', now()->year);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByOperationType($query, $type)
    {
        return $query->where('operation_type', $type);
    }

    public function scopeByBoxCode($query, $boxCode)
    {
        return $query->where('box_code', $boxCode);
    }

    /**
     * Accessors
     */
    public function getOperationSummaryAttribute()
    {
        $summary = [];
        
        if ($this->new_status_id) {
            $summary[] = "Status: " . ($this->newStatus?->nama ?? 'Unknown');
        }
        
        if ($this->new_address) {
            $summary[] = "Address: " . substr($this->new_address, 0, 50) . '...';
        }
        
        return implode(' | ', $summary);
    }

    public function getProcessingTimeAttribute()
    {
        if (!$this->processing_time_ms) return null;
        
        if ($this->processing_time_ms < 1000) {
            return $this->processing_time_ms . 'ms';
        }
        
        return round($this->processing_time_ms / 1000, 2) . 's';
    }

    public function getOperationTypeDisplayAttribute()
    {
        $types = [
            'status' => 'Status Update',
            'address' => 'Address Update', 
            'both' => 'Status & Address'
        ];
        
        return $types[$this->operation_type] ?? 'Unknown';
    }

    /**
     * Static methods for analytics
     */
    public static function getTodayStats()
    {
        $today = static::today();
        
        return [
            'total_operations' => $today->count(),
            'total_items_affected' => $today->sum('items_count'),
            'by_type' => $today->selectRaw('operation_type, COUNT(*) as count, SUM(items_count) as items')
                              ->groupBy('operation_type')
                              ->get()
                              ->mapWithKeys(function($item) {
                                  return [$item->operation_type => [
                                      'operations' => $item->count,
                                      'items' => $item->items
                                  ]];
                              }),
            'by_user' => $today->with('user')
                              ->selectRaw('user_id, COUNT(*) as operations, SUM(items_count) as items')
                              ->groupBy('user_id')
                              ->get()
                              ->map(function($item) {
                                  return [
                                      'user_name' => $item->user?->name ?? 'Unknown',
                                      'operations' => $item->operations,
                                      'items' => $item->items
                                  ];
                              }),
            'avg_processing_time' => $today->whereNotNull('processing_time_ms')
                                          ->avg('processing_time_ms'),
            'top_boxes' => $today->selectRaw('box_code, COUNT(*) as operations, SUM(items_count) as items')
                                ->groupBy('box_code')
                                ->orderBy('operations', 'desc')
                                ->limit(5)
                                ->get()
        ];
    }

    public static function getWeeklyTrend()
    {
        return static::selectRaw('DATE(operation_timestamp) as date, COUNT(*) as operations, SUM(items_count) as items')
                    ->where('operation_timestamp', '>=', now()->subDays(7))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
    }

    public static function getTopPerformers($limit = 10)
    {
        return static::with('user')
                    ->selectRaw('user_id, COUNT(*) as total_operations, SUM(items_count) as total_items, AVG(processing_time_ms) as avg_time')
                    ->thisMonth()
                    ->groupBy('user_id')
                    ->orderBy('total_operations', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function($item) {
                        return [
                            'user_name' => $item->user?->name ?? 'Unknown',
                            'total_operations' => $item->total_operations,
                            'total_items' => $item->total_items,
                            'avg_time' => $item->avg_time ? round($item->avg_time) . 'ms' : null,
                            'efficiency_score' => $item->total_items / max($item->total_operations, 1)
                        ];
                    });
    }

    /**
     * Log a bulk operation
     */
    public static function logOperation($data)
    {
        return static::create([
            'operation_type' => $data['operation_type'],
            'box_code' => $data['box_code'],
            'box_seal_code' => $data['box_seal_code'] ?? null,
            'user_id' => auth()->id(),
            'items_count' => count($data['pengiriman_ids']),
            'pengiriman_ids' => $data['pengiriman_ids'],
            'old_status_id' => $data['old_status_id'] ?? null,
            'new_status_id' => $data['new_status_id'] ?? null,
            'old_address' => $data['old_address'] ?? null,
            'new_address' => $data['new_address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'qr_data' => $data['qr_data'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'operation_timestamp' => now(),
            'processing_time_ms' => $data['processing_time_ms'] ?? null
        ]);
    }
}