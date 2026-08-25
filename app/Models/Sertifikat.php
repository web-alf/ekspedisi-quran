<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class Sertifikat extends Model
{
    use HasFactory;

    protected $table = 'sertifikat';

    protected $fillable = [
        'wakaf_batch_id',
        'donatur_id',
        'pengiriman_id',
        'nomor_sertifikat',
        'template_used',
        'template_id',
        'is_consolidated',
        // 'file_path', // Deprecated: Now using on-demand generation
        'generated_by',
        'generated_at',
        'is_sent',
        'sent_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
        'is_consolidated' => 'boolean',
    ];

    protected $appends = [
        'formatted_generated_at',
        'formatted_sent_at',
    ];

    /**
     * Boot method untuk auto-generate nomor sertifikat
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->nomor_sertifikat)) {
                $model->nomor_sertifikat = $model->generateNomorSertifikat();
            }

            if (empty($model->generated_at)) {
                $model->generated_at = now();
            }
        });
    }

    /**
     * Generate nomor sertifikat unik dengan format CERT-EQ-YYYY-XXXXX
     */
    public function generateNomorSertifikat()
    {
        $tahun = date('Y');
        $prefix = "CERT-EQ-{$tahun}-";
        
        $lastCert = static::where('nomor_sertifikat', 'LIKE', $prefix . '%')
            ->orderBy('nomor_sertifikat', 'DESC')
            ->first();
        
        if ($lastCert) {
            $lastNumber = (int) substr($lastCert->nomor_sertifikat, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Relationship: Sertifikat belongs to WakafBatch
     */
    public function wakafBatch()
    {
        return $this->belongsTo(WakafBatch::class, 'wakaf_batch_id');
    }

    /**
     * Relationship: Sertifikat belongs to Donatur (for consolidated certificates)
     */
    public function donatur()
    {
        return $this->belongsTo(Donatur::class, 'donatur_id');
    }

    /**
     * Legacy Relationship: Sertifikat belongs to Pengiriman
     */
    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class, 'pengiriman_id');
    }

    /**
     * Relationship: Sertifikat belongs to User (generator)
     */
    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Relationship: Sertifikat belongs to CertificateTemplate
     */
    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    /**
     * Accessors
     */
    public function getFormattedGeneratedAtAttribute()
    {
        if (!$this->generated_at) {
            return 'Not Generated';
        }
        
        try {
            return $this->generated_at->format('d M Y H:i');
        } catch (Exception $e) {
            return 'Invalid Date';
        }
    }

    public function getFormattedSentAtAttribute()
    {
        return $this->sent_at ? $this->sent_at->format('d M Y H:i') : null;
    }


    public function getDownloadUrlAttribute()
    {
        return route('admin.certificates.download', $this->id);
    }
 
    public function getViewUrlAttribute()
    {
        return route('admin.certificates.view', $this->id);
    }

    public function getPreviewUrlAttribute()
    {
        return route('admin.certificates.preview', $this->id);
    }

    /**
     * Generate public download token for this certificate
     */
    public function generateDownloadToken()
    {
        $service = app(\App\Services\OnDemandCertificateService::class);
        return $service->generateDownloadToken($this);
    }

    /**
     * Get public download URL with token
     */
    public function getPublicDownloadUrl()
    {
        $service = app(\App\Services\OnDemandCertificateService::class);
        return $service->getPublicDownloadUrl($this);
    }

    /**
     * Helper methods
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Scope: Generated today
     */
    public function scopeGeneratedToday($query)
    {
        return $query->whereDate('generated_at', today());
    }

    /**
     * Scope: Generated this month
     */
    public function scopeGeneratedThisMonth($query)
    {
        return $query->whereMonth('generated_at', now()->month)
                    ->whereYear('generated_at', now()->year);
    }

    /**
     * Scope: Not sent
     */
    public function scopeNotSent($query)
    {
        return $query->where('is_sent', false);
    }


    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        return $this->update([
            'is_sent' => true,
            'sent_at' => now()
        ]);
    }


    /**
     * Get certificate status for display
     */
    public function getStatusAttribute()
    {
        return $this->is_sent ? 'Terkirim' : 'Belum Terkirim';
    }

    /**
     * Get certificate download method info
     */
    public function getDownloadMethodAttribute()
    {
        return 'On-Demand Generation';
    }
}
