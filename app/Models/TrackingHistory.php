<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingHistory extends Model
{
    use HasFactory;

    protected $table = 'tracking_history';

    protected $fillable = [
        'pengiriman_id',
        'status_id',
        'user_id',
        'tanggal_update',
        'lokasi',
        'keterangan',
        'foto_dokumentasi',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'tanggal_update' => 'datetime',
        'foto_dokumentasi' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Relationship: Tracking belongs to Pengiriman
     */
    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class, 'pengiriman_id');
    }

    /**
     * Relationship: Tracking belongs to Status
     */
    public function status()
    {
        return $this->belongsTo(StatusPengiriman::class, 'status_id');
    }

    /**
     * Relationship: Tracking belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get photo URLs
     */
    public function getPhotoUrlsAttribute()
    {
        if (!$this->foto_dokumentasi || !is_array($this->foto_dokumentasi)) {
            return [];
        }

        return array_map(function($item) {
            // Handle array of objects with 'path' key (complex format)
            if (is_array($item) && isset($item['path'])) {
                return asset('storage/' . $item['path']);
            }
            // Handle object with 'path' property
            if (is_object($item) && isset($item->path)) {
                return asset('storage/' . $item->path);
            }
            // Handle simple string path (preferred format)
            if (is_string($item)) {
                // Check if it already starts with 'storage/' or is full URL
                if (str_starts_with($item, 'http') || str_starts_with($item, 'storage/')) {
                    return $item;
                }
                return asset('storage/' . $item);
            }
            // Fallback: convert to string and use as path
            return asset('storage/' . (string)$item);
        }, $this->foto_dokumentasi);
    }

    /**
     * Get formatted location (latitude, longitude)
     */
    public function getCoordinatesAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude
            ];
        }
        return null;
    }

    /**
     * Scope: Filter by pengiriman
     */
    public function scopeByPengiriman($query, $pengirimanId)
    {
        return $query->where('pengiriman_id', $pengirimanId);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tanggal_update', [$startDate, $endDate]);
    }

    /**
     * Scope: Latest first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('tanggal_update', 'desc');
    }
}
