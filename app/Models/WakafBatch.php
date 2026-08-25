<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WakafBatch extends Model
{
    use HasFactory;

    protected $table = 'wakaf_batches';

    protected $fillable = [
        'donatur_id',
        'batch_code',
        'jenis_quran_id',
        'total_quran',
        'tanggal_wakaf',
        'status',
        'catatan',
        'created_by'
    ];

    protected $casts = [
        'tanggal_wakaf' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function donatur()
    {
        return $this->belongsTo(Donatur::class, 'donatur_id');
    }

    public function jenisQuran()
    {
        return $this->belongsTo(JenisQuran::class, 'jenis_quran_id');
    }

    public function pengiriman()
    {
        return $this->hasMany(Pengiriman::class, 'wakaf_batch_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sertifikat()
    {
        return $this->hasOne(Sertifikat::class, 'wakaf_batch_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending_distribution');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Accessors
     */
    public function getDistributionProgressAttribute()
    {
        $total = $this->pengiriman()->count();
        
        // If no pengiriman exists, progress is 0%
        if ($total == 0) {
            return 0;
        }
        
        // Define weighted progress for each status
        $statusWeights = [
            'pemesanan' => 0,      // 0% - Just ordered
            'produksi' => 15,      // 15% - In production  
            'kedatangan' => 35,    // 35% - Arrived at warehouse
            'packing' => 55,       // 55% - Being packed
            'selesai-packing' => 75,   // 75% - Packing completed
            'pengiriman' => 90,    // 90% - In transit/shipping
            'diterima' => 100,     // 100% - Delivered
            'batal' => 0,          // 0% - Cancelled (no progress)
        ];
        
        // Get all pengiriman with their status slugs
        $pengirimanStatuses = $this->pengiriman()
            ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
            ->pluck('status_pengiriman.slug');
        
        // Calculate weighted progress
        $totalProgress = 0;
        foreach ($pengirimanStatuses as $statusSlug) {
            $weight = $statusWeights[$statusSlug] ?? 0;
            $totalProgress += $weight;
        }
        
        $averageProgress = $totalProgress / $total;
        
        return round($averageProgress, 2);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending_distribution' => 'Menunggu Distribusi',
            'in_progress' => 'Sedang Distribusi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => 'Unknown'
        };
    }

    public function getRemainingQuranAttribute()
    {
        return $this->pengiriman()->whereHas('status', function($q) {
            $q->where('slug', 'pemesanan');
        })->count();
    }

    /**
     * Methods
     */
    public function updateStatus()
    {
        $total = $this->pengiriman()->count();
        
        // If no pengiriman exists, status should be pending_distribution
        if ($total == 0) {
            $this->status = 'pending_distribution';
            $this->save();
            return;
        }
        
        // Count pengiriman by status for better decision making
        $statusCounts = $this->pengiriman()
            ->join('status_pengiriman', 'pengiriman.status_id', '=', 'status_pengiriman.id')
            ->selectRaw('status_pengiriman.slug, COUNT(*) as count')
            ->groupBy('status_pengiriman.slug')
            ->pluck('count', 'slug');
        
        $completed = $statusCounts['diterima'] ?? 0;
        $cancelled = $statusCounts['batal'] ?? 0;
        $activeShipments = $total - $cancelled;
        
        // Determine status based on shipment states
        if ($cancelled == $total) {
            // All shipments cancelled
            $this->status = 'cancelled';
        } elseif ($completed == $activeShipments && $activeShipments > 0) {
            // All active shipments completed
            $this->status = 'completed';
        } elseif ($completed > 0 || $activeShipments > 0) {
            // Some progress made or active shipments exist
            $progress = $this->distribution_progress;
            if ($progress > 0) {
                $this->status = 'in_progress';
            } else {
                $this->status = 'pending_distribution';
            }
        } else {
            $this->status = 'pending_distribution';
        }
        
        $this->save();
    }
}
