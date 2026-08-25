<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusPengiriman extends Model
{
    use HasFactory;

    protected $table = 'status_pengiriman';

    protected $fillable = [
        'nama',
        'slug',
        'deskripsi',
        'warna',
        'icon',
        'urutan',
        'is_active',
        'is_final'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_final' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function pengiriman()
    {
        return $this->hasMany(Pengiriman::class, 'status_id');
    }

    public function statusHistoryFrom()
    {
        return $this->hasMany(StatusHistory::class, 'status_from');
    }

    public function statusHistoryTo()
    {
        return $this->hasMany(StatusHistory::class, 'status_to');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan');
    }

    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    public function scopeNotFinal($query)
    {
        return $query->where('is_final', false);
    }

    /**
     * Accessors
     */
    public function getBadgeClassAttribute()
    {
        return match($this->warna) {
            'gray' => 'bg-gray-100 text-gray-800',
            'blue' => 'bg-blue-100 text-blue-800',
            'yellow' => 'bg-yellow-100 text-yellow-800',
            'green' => 'bg-green-100 text-green-800',
            'red' => 'bg-red-100 text-red-800',
            'purple' => 'bg-purple-100 text-purple-800',
            'indigo' => 'bg-indigo-100 text-indigo-800',
            'cyan' => 'bg-cyan-100 text-cyan-800',
            'teal' => 'bg-teal-100 text-teal-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Get status category for dashboard grouping
     */
    public function getCategoryAttribute()
    {
        return match($this->slug) {
            'pemesanan', 'produksi' => 'pending',
            'kedatangan', 'packing', 'selesai-packing', 'pengiriman' => 'in_transit',
            'diterima' => 'completed',
            'batal' => 'cancelled',
            default => 'unknown'
        };
    }

    /**
     * Get dashboard friendly name
     */
    public function getDashboardLabelAttribute()
    {
        return match($this->slug) {
            'pemesanan', 'produksi' => 'Pending/Diproses',
            'kedatangan', 'packing', 'selesai-packing', 'pengiriman' => 'Dalam Perjalanan',
            'diterima' => 'Selesai',
            'batal' => 'Dibatalkan',
            default => $this->nama
        };
    }

    public function getIconAttribute($value)
    {
        // Return database value if exists, otherwise compute based on slug
        if ($value) {
            return $value;
        }
        
        return match($this->slug) {
            'pending' => '⏳',
            'dikemas' => '📦',
            'dikirim' => '🚚',
            'diterima' => '✅',
            'batal' => '❌',
            'pemesanan' => '📝',
            'produksi' => '🏭',
            'kedatangan' => '📦',
            'packing' => '🎁',
            'selesai-packing' => '✅',
            'pengiriman' => '🚚',
            default => '📋'
        };
    }

    /**
     * Static methods
     */
    public static function getDefaultStatusId()
    {
        // Return ID for 'pemesanan' status (first status)
        return static::where('slug', 'pemesanan')->value('id') ?? 1;
    }

    public static function getStatusIdBySlug($slug)
    {
        return static::where('slug', $slug)->value('id');
    }

    public static function getDefaultStatuses()
    {
        return [
            [
                'nama' => 'Proses Pemesanan',
                'slug' => 'pemesanan',
                'deskripsi' => 'Quran sedang dalam proses pemesanan',
                'warna' => 'purple',
                'icon' => '📝',
                'urutan' => 1,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Produksi',
                'slug' => 'produksi',
                'deskripsi' => 'Quran sedang dalam proses produksi',
                'warna' => 'blue',
                'icon' => '🏭',
                'urutan' => 2,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Kedatangan/Penurunan',
                'slug' => 'kedatangan',
                'deskripsi' => 'Quran sudah datang dan dalam proses penurunan',
                'warna' => 'cyan',
                'icon' => '📦',
                'urutan' => 3,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Packing',
                'slug' => 'packing',
                'deskripsi' => 'Quran sedang dalam proses penulisan nama, dokumentasi foto/video, dan wrapping',
                'warna' => 'teal',
                'icon' => '🎁',
                'urutan' => 4,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Selesai Packing',
                'slug' => 'selesai-packing',
                'deskripsi' => 'Quran telah selesai dikemas dan siap untuk dikirim',
                'warna' => 'green',
                'icon' => '✅',
                'urutan' => 5,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Pengiriman',
                'slug' => 'pengiriman',
                'deskripsi' => 'Quran sedang dalam proses pengiriman ke penerima manfaat',
                'warna' => 'yellow',
                'icon' => '🚚',
                'urutan' => 6,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Diterima Penerima',
                'slug' => 'diterima',
                'deskripsi' => 'Quran telah sampai di tangan penerima manfaat',
                'warna' => 'green',
                'icon' => '✅',
                'urutan' => 7,
                'is_active' => true,
                'is_final' => true,
            ],
            [
                'nama' => 'Batal',
                'slug' => 'batal',
                'deskripsi' => 'Pengiriman dibatalkan',
                'warna' => 'red',
                'icon' => '❌',
                'urutan' => 99,
                'is_active' => true,
                'is_final' => true,
            ]
        ];
    }
}
