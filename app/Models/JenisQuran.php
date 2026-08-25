<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisQuran extends Model
{
    use HasFactory;

    protected $table = 'jenis_quran';

    protected $fillable = [
        'kode_jenis',
        'nama_jenis',
        'deskripsi',
        'is_active',
        'default_capacity',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method untuk validation
     */
    protected static function boot()
    {
        parent::boot();

        // Validate capacity sebelum save
        static::saving(function ($model) {
            if (isset($model->default_capacity) && $model->default_capacity <= 0) {
                throw new \InvalidArgumentException(
                    'Default capacity must be a positive number. ' .
                    "Received: {$model->default_capacity}"
                );
            }
        });
    }

    /**
     * Relationships
     */
    public function pengiriman()
    {
        return $this->hasMany(Pengiriman::class, 'jenis_quran_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Accessors
     */
    public function getBadgeClassAttribute()
    {
        return match($this->kode_jenis) {
            'A5' => 'bg-blue-100 text-blue-800',
            'A6' => 'bg-purple-100 text-purple-800',
            'IQRO' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Get default capacity for this jenis
     *
     * ✅ Now reads from database column instead of hardcoded values
     * ✅ Fallback to 20 if not set (backward compatibility)
     */
    public function getDefaultCapacity()
    {
        return $this->default_capacity ?? 20;
    }

    /**
     * Get capacity info with description
     */
    public function getCapacityInfoAttribute()
    {
        $capacity = $this->getDefaultCapacity();
        $description = match($this->kode_jenis) {
            'A5' => "Al-Qur'an ukuran A5 - {$capacity} eks per box",
            'A6' => "Al-Qur'an ukuran A6 - {$capacity} eks per box", 
            'IQRO' => "Buku Iqro - {$capacity} eks per box",
            default => "{$this->nama_jenis} - {$capacity} eks per box"
        };
        
        return [
            'capacity' => $capacity,
            'description' => $description
        ];
    }
    public static function getDefaultJenis()
    {
        return [
            [
                'kode_jenis' => 'A5',
                'nama_jenis' => 'Al-Qur\'an A5',
                'deskripsi' => 'Al-Qur\'an ukuran A5 (14.8 x 21 cm)',
                'is_active' => true
            ],
            [
                'kode_jenis' => 'A6',
                'nama_jenis' => 'Al-Qur\'an A6',
                'deskripsi' => 'Al-Qur\'an ukuran A6 (10.5 x 14.8 cm)',
                'is_active' => true
            ],
            [
                'kode_jenis' => 'IQRO',
                'nama_jenis' => 'Iqro',
                'deskripsi' => 'Buku Iqro untuk belajar membaca Al-Qur\'an',
                'is_active' => true
            ]
        ];
    }
}
