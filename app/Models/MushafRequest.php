<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MushafRequest extends Model
{
    use HasFactory;

    // Konstanta untuk kategori lembaga
    public const KATEGORI_LEMBAGA = [
        'PENDIDIKAN' => [
            'Pondok Pesantren',
            'Rumah Tahfidz/Rumah Qur\'an',
            'TPQ/TPA/Madin',
            'Sekolah/Madrasah',
        ],
        'KOMUNITAS' => [
            'Masjid/Mushola/Majelis Taklim/Jamaah Masjid',
            'Masyarakat/Jamaah Alfatihah',
            'Organisasi/Paguyuban/Event Sosial/Komunitas',
            'Santri & Karyawan Alfatihah',
        ],
        'SOSIAL_PEMERINTAHAN' => [
            'Yayasan',
            'Panti Asuhan/Anak Yatim',
            'RT/RW/Pemerintah Desa/Kecamatan',
            'Lembaga Lainnya',
        ],
        'PENERIMA_KHUSUS' => [
            'Muallaf',
            'Penerima Manfaat Khusus Lainnya',
        ],
    ];

    public const KATEGORI_LABELS = [
        'PENDIDIKAN' => 'Lembaga Pendidikan & Pembinaan',
        'KOMUNITAS' => 'Komunitas & Dakwah Kemasyarakatan',
        'SOSIAL_PEMERINTAHAN' => 'Lembaga Sosial & Pemerintahan',
        'PENERIMA_KHUSUS' => 'Penerima Manfaat Khusus',
    ];

    protected $fillable = [
        'no_request',
        'nama_lembaga',
        'kategori_lembaga',
        'alamat_lengkap',
        // New detailed address fields
        'provinsi',
        'provinsi_id',
        'kota_kabupaten',
        'kota_kabupaten_id',
        'kecamatan',
        'kecamatan_id',
        'kelurahan_desa',
        'kelurahan_desa_id',
        'kode_pos',
        'alamat_detail',
        'latitude',
        'longitude',
        // Existing fields
        'nama_pengurus_1',
        'jabatan_pengurus_1',
        'whatsapp_pengurus_1',
        'nama_pengurus_2',
        'jabatan_pengurus_2',
        'whatsapp_pengurus_2',
        'urgensi_request',
        'jumlah_mushaf',
        'jumlah_mushaf_a5',
        'jumlah_mushaf_a6',
        'jumlah_iqra',
        'jumlah_mushaf_approved',
        'jumlah_mushaf_a5_approved',
        'jumlah_mushaf_a6_approved',
        'jumlah_iqra_approved',
        'catatan_perubahan_jumlah',
        'jenis_mushaf_diminta',
        'sumber_info',
        'foto_santri_path',
        'foto_lembaga_path',
        'file_nama_santri_path',
        'status',
        'catatan_admin',
        'approved_at',
        'rejected_at',
        'pengiriman_id',
        'reviewed_by',
    ];

    protected $casts = [
        'jenis_mushaf_diminta' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Boot method untuk auto-generate no_request
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->no_request)) {
                $model->no_request = $model->generateUniqueNoRequest();
            }

            // Auto-generate alamat_lengkap jika fields detail diisi
            if (empty($model->alamat_lengkap)) {
                $model->alamat_lengkap = $model->generateAlamatLengkap();
            }
        });

        static::updating(function ($model) {
            // Update alamat_lengkap jika ada perubahan di fields detail
            if ($model->isDirty(['provinsi', 'kota_kabupaten', 'kecamatan', 'kelurahan_desa', 'alamat_detail'])) {
                $model->alamat_lengkap = $model->generateAlamatLengkap();
            }
        });
    }

    /**
     * Generate alamat lengkap dari komponen alamat detail
     */
    public function generateAlamatLengkap()
    {
        $components = array_filter([
            $this->alamat_detail,
            $this->kelurahan_desa,
            $this->kecamatan,
            $this->kota_kabupaten,
            $this->provinsi,
            $this->kode_pos,
        ]);

        return implode(', ', $components);
    }

    /**
     * Generate nomor request unik dengan format REQ-YYYY-XXXXX
     *
     * Race-condition safe: memakai database sequence (NoRequestSequence)
     * dengan lockForUpdate + transaction, sehingga dua permintaan publik
     * yang datang paralel tidak akan menghasilkan nomor yang sama.
     */
    public function generateUniqueNoRequest()
    {
        $tahun = (int) date('Y');

        // Ambil nomor berikutnya secara atomic dari sequence table
        $nextNumber = \App\Models\NoRequestSequence::getNextNumber($tahun);

        return "REQ-{$tahun}-".str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Relationship: Request reviewed by User
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Relationship: Request processed to Pengiriman
     */
    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class);
    }

    /**
     * Accessors untuk status
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'pending' => 'Menunggu Review',
            'reviewed' => 'Sedang Direview',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'processed' => 'Sudah Diproses',
            'completed' => 'Selesai',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'reviewed' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'processed' => 'purple',
            'completed' => 'emerald',
            default => 'gray'
        };
    }

    /**
     * Accessor untuk total mushaf yang diminta
     */
    public function getTotalMushafAttribute()
    {
        return $this->jumlah_mushaf_a5 + $this->jumlah_mushaf_a6 + $this->jumlah_iqra;
    }

    /**
     * Accessor untuk total mushaf Al-Qur'an saja (A5 + A6)
     */
    public function getTotalMushafQuranAttribute()
    {
        return $this->jumlah_mushaf_a5 + $this->jumlah_mushaf_a6;
    }

    /**
     * Accessor untuk total mushaf yang disetujui
     */
    public function getTotalMushafApprovedAttribute()
    {
        return ($this->jumlah_mushaf_a5_approved ?? $this->jumlah_mushaf_a5)
            + ($this->jumlah_mushaf_a6_approved ?? $this->jumlah_mushaf_a6)
            + ($this->jumlah_iqra_approved ?? $this->jumlah_iqra);
    }

    /**
     * Accessor untuk total mushaf Al-Qur'an yang disetujui (A5 + A6)
     */
    public function getTotalMushafQuranApprovedAttribute()
    {
        return ($this->jumlah_mushaf_a5_approved ?? $this->jumlah_mushaf_a5)
            + ($this->jumlah_mushaf_a6_approved ?? $this->jumlah_mushaf_a6);
    }

    /**
     * Check apakah ada perubahan jumlah antara yang diajukan dan disetujui
     */
    public function getHasQuantityChangeAttribute()
    {
        return $this->total_mushaf_approved !== $this->total_mushaf;
    }

    /**
     * Get persentase perubahan jumlah
     */
    public function getQuantityChangePercentageAttribute()
    {
        if ($this->total_mushaf === 0) {
            return 0;
        }

        $difference = $this->total_mushaf_approved - $this->total_mushaf;

        return round(($difference / $this->total_mushaf) * 100, 2);
    }

    /**
     * Accessor untuk daftar jenis mushaf dengan jumlah
     */
    public function getJenisMushafListAttribute()
    {
        if (! $this->jenis_mushaf_diminta) {
            return '';
        }

        $jenis = [];
        if (in_array('A5', $this->jenis_mushaf_diminta) && $this->jumlah_mushaf_a5 > 0) {
            $jenis[] = "Al-Qur'an A5 ({$this->jumlah_mushaf_a5} buah)";
        }
        if (in_array('A6', $this->jenis_mushaf_diminta) && $this->jumlah_mushaf_a6 > 0) {
            $jenis[] = "Al-Qur'an A6 ({$this->jumlah_mushaf_a6} buah)";
        }
        if (in_array('IQRA', $this->jenis_mushaf_diminta) && $this->jumlah_iqra > 0) {
            $jenis[] = "IQRA' ({$this->jumlah_iqra} buah)";
        }

        return implode(', ', $jenis);
    }

    /**
     * Accessor untuk alamat lengkap yang terformat
     */
    public function getFormattedAddressAttribute()
    {
        return $this->generateAlamatLengkap();
    }

    /**
     * Accessor untuk koordinat sebagai array
     */
    public function getCoordinatesAttribute()
    {
        if (! $this->latitude || ! $this->longitude) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }

    /**
     * Accessor untuk kategori lembaga dengan emoji
     */
    public function getKategoriLembagaWithEmojiAttribute()
    {
        $emojiMap = [
            'Pondok Pesantren' => '🎓 Pondok Pesantren',
            'Rumah Tahfidz/Rumah Qur\'an' => '🎓 Rumah Tahfidz/Rumah Qur\'an',
            'TPQ/TPA/Madin' => '🎓 TPQ/TPA/Madin',
            'Sekolah/Madrasah' => '🎓 Sekolah/Madrasah',
            'Masjid/Mushola/Majelis Taklim/Jamaah Masjid' => '🕌 Masjid/Mushola/Majelis Taklim/Jamaah Masjid',
            'Masyarakat/Jamaah Alfatihah' => '🕌 Masyarakat/Jamaah Alfatihah',
            'Organisasi/Paguyuban/Event Sosial/Komunitas' => '🕌 Organisasi/Paguyuban/Event Sosial/Komunitas',
            'Santri & Karyawan Alfatihah' => '🕌 Santri & Karyawan Alfatihah',
            'Yayasan' => '🏛️ Yayasan',
            'Panti Asuhan/Anak Yatim' => '🏛️ Panti Asuhan/Anak Yatim',
            'RT/RW/Pemerintah Desa/Kecamatan' => '🏛️ RT/RW/Pemerintah Desa/Kecamatan',
            'Lembaga Lainnya' => '🏛️ Lembaga Lainnya',
            'Muallaf' => '🤲 Muallaf',
            'Penerima Manfaat Khusus Lainnya' => '🤲 Penerima Manfaat Khusus Lainnya',
        ];

        return $emojiMap[$this->kategori_lembaga] ?? $this->kategori_lembaga;
    }

    /**
     * Accessor untuk grup kategori
     */
    public function getGrupKategoriAttribute()
    {
        foreach (self::KATEGORI_LEMBAGA as $grup => $kategoris) {
            if (in_array($this->kategori_lembaga, $kategoris)) {
                return self::KATEGORI_LABELS[$grup];
            }
        }

        return 'Lainnya';
    }

    /**
     * Accessor untuk check apakah punya koordinat
     */
    public function getHasCoordinatesAttribute()
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /**
     * URL untuk foto santri
     */
    public function getFotoSantriUrlAttribute()
    {
        return $this->foto_santri_path ? Storage::url($this->foto_santri_path) : null;
    }

    /**
     * URL untuk foto lembaga
     */
    public function getFotoLembagaUrlAttribute()
    {
        return $this->foto_lembaga_path ? Storage::url($this->foto_lembaga_path) : null;
    }

    /**
     * URL untuk file nama santri
     */
    public function getFileNamaSantriUrlAttribute()
    {
        return $this->file_nama_santri_path ? Storage::url($this->file_nama_santri_path) : null;
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope untuk filtering by kategori lembaga
     */
    public function scopeByKategori($query, $kategori)
    {
        return $query->where('kategori_lembaga', $kategori);
    }

    public function scopeByGrupKategori($query, $grup)
    {
        $mapping = [
            'pendidikan' => self::KATEGORI_LEMBAGA['PENDIDIKAN'],
            'komunitas' => self::KATEGORI_LEMBAGA['KOMUNITAS'],
            'sosial' => self::KATEGORI_LEMBAGA['SOSIAL_PEMERINTAHAN'],
            'khusus' => self::KATEGORI_LEMBAGA['PENERIMA_KHUSUS'],
        ];

        if (isset($mapping[$grup])) {
            return $query->whereIn('kategori_lembaga', $mapping[$grup]);
        }

        return $query;
    }

    public function scopeWithCoordinates($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Scope untuk radius search (requires raw SQL)
     * Menggunakan Haversine formula untuk mencari berdasarkan jarak
     */
    public function scopeWithinRadius($query, $latitude, $longitude, $radiusKm = 10)
    {
        $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        return $query->select('*')
            ->selectRaw("{$haversine} AS distance", [$latitude, $longitude, $latitude])
            ->whereRaw("{$haversine} < ?", [$latitude, $longitude, $latitude, $radiusKm])
            ->orderBy('distance');
    }

    /**
     * Helper methods untuk update status
     */
    public function approve($adminId, $catatan = null)
    {
        // Guard: hanya request yang masih pending/reviewed yang bisa disetujui.
        // Mencegah request yang sudah rejected/approved/processed/completed di-approve ulang
        // (race condition: dua admin approve bersamaan, atau approve setelah reject).
        if (! in_array($this->status, ['pending', 'reviewed'], true)) {
            throw new \Exception("Permintaan dengan status {$this->status} tidak dapat disetujui");
        }

        return $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'reviewed_by' => $adminId,
            'catatan_admin' => $catatan,
        ]);
    }

    public function reject($adminId, $catatan)
    {
        // Guard: hanya request yang masih pending/reviewed yang bisa ditolak.
        if (! in_array($this->status, ['pending', 'reviewed'], true)) {
            throw new \Exception("Permintaan dengan status {$this->status} tidak dapat ditolak");
        }

        return $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'reviewed_by' => $adminId,
            'catatan_admin' => $catatan,
        ]);
    }

    public function markAsProcessed($pengirimanId, $adminId)
    {
        return $this->update([
            'status' => 'processed',
            'pengiriman_id' => $pengirimanId,
            'reviewed_by' => $adminId,
        ]);
    }

    /**
     * Helper method untuk geocoding (jika diperlukan integrasi dengan Google Maps API)
     */
    public function geocodeAddress()
    {
        // Implementasi geocoding bisa ditambahkan di sini
        // Menggunakan Google Maps Geocoding API atau service lainnya
        // untuk otomatis mendapatkan koordinat dari alamat

        return [
            'success' => false,
            'message' => 'Geocoding not implemented yet',
        ];
    }

    /**
     * Helper method untuk mendapatkan statistik berdasarkan kategori
     */
    public static function getKategoriStats()
    {
        return [
            'by_kategori' => static::selectRaw('kategori_lembaga, COUNT(*) as total')
                ->whereNotNull('kategori_lembaga')
                ->groupBy('kategori_lembaga')
                ->orderBy('total', 'desc')
                ->get(),

            'by_grup_kategori' => [
                'pendidikan' => static::byGrupKategori('pendidikan')->count(),
                'komunitas' => static::byGrupKategori('komunitas')->count(),
                'sosial' => static::byGrupKategori('sosial')->count(),
                'khusus' => static::byGrupKategori('khusus')->count(),
            ],

            'total_by_kategori' => static::whereNotNull('kategori_lembaga')->count(),
            'total_requests' => static::count(),
        ];
    }
}
