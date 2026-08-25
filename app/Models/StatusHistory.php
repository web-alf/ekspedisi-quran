<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    use HasFactory;

    protected $table = 'status_histories';

    protected $fillable = [
        'pengiriman_id',
        'status_from',
        'status_to',
        'catatan',
        'lokasi',
        'latitude',
        'longitude',
        'dokumentasi',
        'created_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'dokumentasi' => 'array', // Cast to array for save/retrieve
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8'
    ];

    /**
     * Relationships
     */
    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function statusFrom()
    {
        return $this->belongsTo(StatusPengiriman::class, 'status_from');
    }

    public function statusTo()
    {
        return $this->belongsTo(StatusPengiriman::class, 'status_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accessors
     */
    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    public function getStatusChangeAttribute()
    {
        $from = $this->statusFrom ? $this->statusFrom->nama : 'Unknown';
        $to = $this->statusTo ? $this->statusTo->nama : 'Unknown';
        
        return "{$from} → {$to}";
    }
    
    /**
     * Helper method to get documentation photo URLs
     * Use this instead of accessing dokumentasi_urls attribute
     */
    public function getDocumentationPhotos()
    {
        $raw = $this->getRawOriginal('dokumentasi');

        if (!$raw) {
            return [];
        }

        $data = json_decode($raw, true);

        // Handle double encoding
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $item) {
            if (is_string($item)) {
                $result[] = asset('storage/' . $item);
            } elseif (is_array($item) && isset($item['path'])) {
                $result[] = isset($item['url']) ? $item['url'] : asset('storage/' . $item['path']);
            }
        }

        return $result;
    }

    /**
     * Accessor for dokumentasi_urls attribute
     * Calls helper method
     */
    public function getDokumentasiUrlsAttribute()
    {
        return $this->getDocumentationPhotos();
    }
    
    public function getHasLocationAttribute()
    {
        return !empty($this->lokasi) || ($this->latitude && $this->longitude);
    }
    
    public function getLocationDisplayAttribute()
    {
        if (!empty($this->lokasi)) {
            return $this->lokasi;
        }
        
        if ($this->latitude && $this->longitude) {
            return "Lat: {$this->latitude}, Lng: {$this->longitude}";
        }
        
        return null;
    }
}
