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
                    'Default capacity must be a positive number. '.
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
        return match ($this->kode_jenis) {
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
     * Apakah stok jenis ini dipisah per varian terjemahan (A5/A6)? IQRA/IQRO tidak.
     */
    public function punyaVarianTerjemahan(): bool
    {
        return in_array($this->kode_jenis, config('pengiriman.stok_varian_jenis', []), true);
    }

    /**
     * Sebutan pendek jenis untuk pilihan tugas packing: kode ukuran (A5/A6), atau "Iqro".
     */
    public function labelSingkat(): string
    {
        return in_array($this->kode_jenis, ['IQRA', 'IQRO'], true) ? 'Iqro' : $this->kode_jenis;
    }

    /**
     * "A5 Terjemahan" / "A5 Tanpa Terjemahan"; jenis tanpa varian cukup sebutan pendeknya.
     */
    public function labelDenganVarian(?bool $terjemahan): string
    {
        if (! $this->punyaVarianTerjemahan() || $terjemahan === null) {
            return $this->labelSingkat();
        }

        return $this->labelSingkat().($terjemahan ? ' Terjemahan' : ' Tanpa Terjemahan');
    }

    /**
     * Nilai satu pilihan jenis tugas: "<id jenis>:<1|0|->" (1 terjemahan, 0 tanpa, - tanpa varian).
     */
    public static function nilaiPilihanTugas(int $jenisQuranId, ?bool $terjemahan): string
    {
        return $jenisQuranId.':'.match ($terjemahan) {
            true => '1',
            false => '0',
            null => '-',
        };
    }

    /**
     * Pilihan "Jenis Mushaf" di modal Assign Target Harian, berurutan: tiap jenis bervarian
     * muncul dua kali (tanpa terjemahan lalu terjemahan), jenis lain sekali.
     *
     * @return array<int, array{nilai: string, jenis_quran_id: int, terjemahan: bool|null, label: string}>
     */
    public static function pilihanTugasPacking(): array
    {
        return static::active()
            ->orderBy('id')
            ->get(['id', 'kode_jenis'])
            ->flatMap(fn (JenisQuran $jenis) => collect($jenis->punyaVarianTerjemahan() ? [false, true] : [null])
                ->map(fn (?bool $terjemahan) => [
                    'nilai' => static::nilaiPilihanTugas($jenis->id, $terjemahan),
                    'jenis_quran_id' => $jenis->id,
                    'terjemahan' => $terjemahan,
                    'label' => $jenis->labelDenganVarian($terjemahan),
                ]))
            ->values()
            ->all();
    }

    /**
     * Jenis aktif untuk form stok & transfer stok, beserta penanda `ada_varian` yang
     * menentukan apakah pilihan terjemahan ditawarkan.
     *
     * @return array<int, array{id: int, kode_jenis: string, nama_jenis: string, default_capacity: int|null, ada_varian: bool}>
     */
    public static function daftarUntukFormStok(): array
    {
        return static::active()
            ->orderBy('id')
            ->get(['id', 'kode_jenis', 'nama_jenis', 'default_capacity'])
            ->map(fn (JenisQuran $jenis) => [
                ...$jenis->only(['id', 'kode_jenis', 'nama_jenis', 'default_capacity']),
                'ada_varian' => $jenis->punyaVarianTerjemahan(),
            ])
            ->all();
    }

    /**
     * Get capacity info with description
     */
    public function getCapacityInfoAttribute()
    {
        $capacity = $this->getDefaultCapacity();
        $description = match ($this->kode_jenis) {
            'A5' => "Al-Qur'an ukuran A5 - {$capacity} eks per box",
            'A6' => "Al-Qur'an ukuran A6 - {$capacity} eks per box",
            'IQRO' => "Buku Iqro - {$capacity} eks per box",
            default => "{$this->nama_jenis} - {$capacity} eks per box"
        };

        return [
            'capacity' => $capacity,
            'description' => $description,
        ];
    }

    public static function getDefaultJenis()
    {
        return [
            [
                'kode_jenis' => 'A5',
                'nama_jenis' => 'Al-Qur\'an A5',
                'deskripsi' => 'Al-Qur\'an ukuran A5 (14.8 x 21 cm)',
                'is_active' => true,
            ],
            [
                'kode_jenis' => 'A6',
                'nama_jenis' => 'Al-Qur\'an A6',
                'deskripsi' => 'Al-Qur\'an ukuran A6 (10.5 x 14.8 cm)',
                'is_active' => true,
            ],
            [
                'kode_jenis' => 'IQRO',
                'nama_jenis' => 'Iqro',
                'deskripsi' => 'Buku Iqro untuk belajar membaca Al-Qur\'an',
                'is_active' => true,
            ],
        ];
    }
}
