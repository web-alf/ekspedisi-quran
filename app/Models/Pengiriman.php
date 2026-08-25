<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengiriman extends Model
{
    use HasFactory;

    protected $table = 'pengiriman';

    protected $fillable = [
        'no_resi',
        'donatur_id',
        'wakaf_batch_id',
        'sequence_in_batch',
        'donation_id',
        'wakaf_item_id',
        'jenis_quran_id',
        'jumlah_quran',
        'tanggal_wakaf',
        'status_id',
        'qr_code_path',
        'qr_code_data',
        'alamat_tujuan',
        'nama_penerima',
        'nama_lembaga',
        'no_hp_penerima',
        'catatan',
        'received_at',
        'received_by',
        'receiver_contact',
        'delivery_proof',
        'delivery_notes',
        'sertifikat_generated',
        'sertifikat_path',
        'created_by',
    ];

    protected $casts = [
        'tanggal_wakaf' => 'date',
        'received_at' => 'datetime',
        'delivery_proof' => 'array',
        'sertifikat_generated' => 'boolean',
    ];

    /**
     * 🚀 AUTO-GENERATE NO_RESI
     * Boot method untuk auto-generate no_resi saat create
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto generate no_resi jika belum ada
            if (empty($model->no_resi)) {
                $model->no_resi = $model->generateUniqueNoResi();
            }
        });
    }

    /**
     * Generate nomor resi unik dengan format EQ-YYYY-XXXXX
     *
     * ✅ Race-condition safe menggunakan database sequence
     * ✅ Atomic increment dengan lockForUpdate
     * ✅ No collision possible
     */
    public function generateUniqueNoResi()
    {
        $tahun = (int) date('Y');

        // Get next number dari sequence table (atomic & race-safe)
        $nextNumber = NoResiSequence::getNextNumber($tahun);

        // Format: EQ-YYYY-XXXXX
        return "EQ-{$tahun}-".str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @deprecated Use generateUniqueNoResi() instead
     * Kept for backward compatibility, but now uses new sequence system
     */
    private function generateNoResi()
    {
        return $this->generateUniqueNoResi();
    }

    /**
     * Relationship: Pengiriman belongs to Donatur
     */
    public function donatur()
    {
        return $this->belongsTo(Donatur::class, 'donatur_id');
    }

    /**
     * Relationship: Pengiriman belongs to WakafBatch
     */
    public function wakafBatch()
    {
        return $this->belongsTo(WakafBatch::class, 'wakaf_batch_id');
    }

    /**
     * Relationship: Pengiriman belongs to JenisQuran
     */
    public function jenisQuran()
    {
        return $this->belongsTo(JenisQuran::class, 'jenis_quran_id');
    }

    /**
     * Relationship: Pengiriman belongs to StatusPengiriman
     */
    public function status()
    {
        return $this->belongsTo(StatusPengiriman::class, 'status_id');
    }

    /**
     * Relationship: Pengiriman belongs to Donation
     */
    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * Relationship: Pengiriman belongs to WakafItem
     */
    public function wakafItem()
    {
        return $this->belongsTo(WakafItem::class);
    }

    /**
     * Relationship: Pengiriman belongs to User (creator)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🆕 Relationship: Pengiriman has many StatusHistory
     */
    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class, 'pengiriman_id');
    }

    /**
     * Relationship: Pengiriman has many TrackingHistory
     */
    public function trackingHistory()
    {
        return $this->hasMany(TrackingHistory::class);
    }

    /**
     * 🆕 Relationship: Pengiriman has one Sertifikat
     */
    public function sertifikat()
    {
        return $this->hasOne(Sertifikat::class, 'pengiriman_id');
    }

    /**
     * Relationship: Pengiriman has one MushafRequest (reverse lookup)
     */
    public function mushafRequest()
    {
        return $this->hasOne(MushafRequest::class, 'pengiriman_id');
    }

    /**
     * 📦 Relationship: Pengiriman has one DailyPackingTaskItem
     */
    public function dailyPackingTaskItem()
    {
        return $this->hasOne(DailyPackingTaskItem::class);
    }

    /**
     * 📦 Relationship: Pengiriman has one PackingItem
     */
    public function packingItem()
    {
        return $this->hasOne(PackingItem::class);
    }

    /**
     * Scope: Has resi number
     */
    public function scopeHasResi($query)
    {
        return $query->whereNotNull('no_resi');
    }

    /**
     * Scope: No resi yet
     */
    public function scopeNoResi($query)
    {
        return $query->whereNull('no_resi');
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope: Pending (default status)
     */
    public function scopePending($query)
    {
        return $query->where('status_id', StatusPengiriman::getDefaultStatusId());
    }

    /**
     * Scope: Has alamat tujuan
     */
    public function scopeHasAlamat($query)
    {
        return $query->whereNotNull('alamat_tujuan');
    }

    /**
     * Scope: No alamat yet
     */
    public function scopeNoAlamat($query)
    {
        return $query->whereNull('alamat_tujuan');
    }

    /**
     * Accessors
     */
    public function getStatusNameAttribute()
    {
        return $this->status ? $this->status->nama : 'Unknown';
    }

    public function getStatusColorAttribute()
    {
        return $this->status ? $this->status->warna : 'gray';
    }

    public function getFormattedTanggalWakafAttribute()
    {
        return $this->tanggal_wakaf ? $this->tanggal_wakaf->format('d/m/Y') : null;
    }

    public function getHasAlamatAttribute()
    {
        return ! empty($this->alamat_tujuan);
    }

    public function getTrackingUrlAttribute()
    {
        return route('public.tracking', $this->no_resi);
    }

    public function getHasSertifikatAttribute()
    {
        return $this->sertifikat()->exists();
    }

    public function getSertifikatNumberAttribute()
    {
        return $this->sertifikat?->nomor_sertifikat;
    }

    /**
     * Helper: Check if no_resi format is valid
     */
    public function isValidNoResiFormat(?string $noResi = null)
    {
        $resi = $noResi ?: $this->no_resi;

        return preg_match('/^EQ-\d{4}-\d{5}$/', $resi);
    }

    /**
     * Helper: Parse no_resi information
     */
    public function parseNoResi(?string $noResi = null)
    {
        $resi = $noResi ?: $this->no_resi;

        if (! $this->isValidNoResiFormat($resi)) {
            return null;
        }

        $parts = explode('-', $resi);

        return [
            'prefix' => $parts[0], // EQ
            'tahun' => $parts[1],  // YYYY
            'nomor' => (int) $parts[2], // XXXXX
            'nomor_urut' => $parts[2], // XXXXX dengan leading zero
        ];
    }

    /**
     * Helper: Update status dengan history
     */
    public function updateStatus($newStatusId, ?string $catatan = null, ?int $userId = null)
    {
        $oldStatus = $this->status_id;

        // Update status
        $this->update(['status_id' => $newStatusId]);

        // Create status history
        StatusHistory::create([
            'pengiriman_id' => $this->id,
            'status_from' => $oldStatus,
            'status_to' => $newStatusId,
            'catatan' => $catatan,
            'created_by' => $userId ?: auth()->id(),
        ]);

        return $this;
    }

    /**
     * Helper: Generate QR Code data
     */
    public function generateQRData()
    {
        return [
            'no_resi' => $this->no_resi,
            'donatur' => $this->donatur->nama_donatur ?? 'Unknown',
            'jenis_quran' => $this->jenisQuran->nama_jenis ?? 'Unknown',
            'jumlah_quran' => $this->jumlah_quran,
            'tanggal_wakaf' => $this->formatted_tanggal_wakaf,
            'url' => $this->tracking_url,
            'timestamp' => now()->timestamp,
        ];
    }
}
