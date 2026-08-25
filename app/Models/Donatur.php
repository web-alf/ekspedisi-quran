<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donatur extends Model
{
    use HasFactory;

    protected $table = 'donatur';

    protected $fillable = [
        'kode_donatur',
        'nama_donatur',
        'no_hp',
        'email_donatur',
        'alamat_donatur',
        'jenis_wakaf_dipilih',
        'total_a5_count',
        'total_a6_count',
        'total_iqra_count',
        'donation_count',
        'donation_date',
        'doa_untuk_semua',
        'prayer_mode',
        'created_by',
    ];

    protected $casts = [
        'jenis_wakaf_dipilih' => 'array',
        'donation_date' => 'date:Y-m-d',
        'prayer_mode' => 'string',
    ];

    /**
     * Relationship: Donatur belongs to User (creator)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Donatur has many Pengiriman
     */
    public function pengiriman()
    {
        return $this->hasMany(Pengiriman::class, 'donatur_id');
    }

    /**
     * Relationship: Donatur has many WakafBatch
     */
    public function wakafBatches()
    {
        return $this->hasMany(WakafBatch::class, 'donatur_id');
    }

    /**
     * Relationship: Donatur has many WakafItem
     */
    public function wakafItems()
    {
        return $this->hasMany(WakafItem::class, 'donatur_id');
    }

    /**
     * Relationship: Donatur has many Sertifikat (consolidated certificates)
     */
    public function sertifikat()
    {
        return $this->hasMany(Sertifikat::class, 'donatur_id');
    }

    /**
     * Computed attribute: Actual A5 count based on wakaf_items
     */
    protected function computedA5Count(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wakafItems()->where('wakaf_type', 'A5')->count(),
        );
    }

    /**
     * Computed attribute: Actual A6 count based on wakaf_items
     */
    protected function computedA6Count(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wakafItems()->where('wakaf_type', 'A6')->count(),
        );
    }

    /**
     * Computed attribute: Actual IQRA count based on wakaf_items
     */
    protected function computedIqraCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->wakafItems()->where('wakaf_type', 'IQRA')->count(),
        );
    }

    /**
     * Accessor: Get actual A5 count from wakaf_items relationship
     */
    public function getActualA5CountAttribute(): int
    {
        return $this->wakafItems()->where('wakaf_type', 'A5')->count();
    }

    /**
     * Accessor: Get actual A6 count from wakaf_items relationship
     */
    public function getActualA6CountAttribute(): int
    {
        return $this->wakafItems()->where('wakaf_type', 'A6')->count();
    }

    /**
     * Accessor: Get actual IQRA count from wakaf_items relationship
     */
    public function getActualIqraCountAttribute(): int
    {
        return $this->wakafItems()->where('wakaf_type', 'IQRA')->count();
    }

    /**
     * Get total wakaf count (number of wakaf transactions/batches)
     */
    public function getTotalWakafAttribute()
    {
        return $this->wakafBatches()->count();
    }

    /**
     * Get total Al-Quran donated by this donatur
     */
    public function getTotalQuranAttribute()
    {
        return $this->actual_a5_count + $this->actual_a6_count + $this->actual_iqra_count;
    }

    /**
     * Get total pengiriman/resi count
     */
    public function getTotalPengirimanAttribute()
    {
        return $this->pengiriman()->count();
    }

    /**
     * Sync wakaf items with prayer mode - updates wakif_name based on prayer_mode
     * Only affects items with status = 'pending'
     */
    public function syncWakafItemsWithPrayerMode(): int
    {
        $updatedCount = 0;

        $pendingItems = $this->wakafItems()->where('status', 'pending')->get();

        foreach ($pendingItems as $item) {
            $needsUpdate = false;
            $newWakifName = null;
            $newDoaRequest = '';

            switch ($this->prayer_mode) {
                case 'semua_donatur':
                    $newWakifName = $this->nama_donatur;
                    $newDoaRequest = $this->doa_untuk_semua ?? '';
                    break;

                case 'customize_individual':
                    // Keep existing wakif_name for individual customization
                    $newWakifName = $item->wakif_name;
                    $newDoaRequest = $item->doa_request;
                    break;

                case 'mixed':
                    // For mixed mode, only update if wakif_name is empty or equals donatur name
                    if (empty($item->wakif_name) || $item->wakif_name === $this->nama_donatur) {
                        $newWakifName = $this->nama_donatur;
                        $newDoaRequest = $this->doa_untuk_semua ?? '';
                    } else {
                        $newWakifName = $item->wakif_name;
                        $newDoaRequest = $item->doa_request;
                    }
                    break;
            }

            if ($item->wakif_name !== $newWakifName || $item->doa_request !== $newDoaRequest) {
                $item->update([
                    'wakif_name' => $newWakifName,
                    'doa_request' => $newDoaRequest,
                ]);
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Check if quantity can be reduced for a specific wakaf type
     */
    public function canReduceQuantity(string $type, int $amount): bool
    {
        $processedCount = $this->getProcessedItemsCount($type);
        $currentCount = $this->wakafItems()->where('wakaf_type', $type)->count();
        $remainingCount = $currentCount - $processedCount;

        return $remainingCount >= $amount;
    }

    /**
     * Get count of processed items for a specific wakaf type
     */
    public function getProcessedItemsCount(string $type): int
    {
        return $this->wakafItems()
            ->where('wakaf_type', $type)
            ->whereIn('status', ['processed', 'shipped', 'delivered'])
            ->count();
    }

    /**
     * Scope: Search by donatur name, phone, or code
     */
    public function scopeByDonatur($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nama_donatur', 'like', "%{$search}%")
                ->orWhere('kode_donatur', 'like', "%{$search}%")
                ->orWhere('no_hp', 'like', "%{$search}%")
                ->orWhere('email_donatur', 'like', "%{$search}%");
        });
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeByDateRange($query, ?string $startDate = null, ?string $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('donation_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('donation_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Generate wakaf items for this donation
     */
    public function generateWakafItems()
    {
        $items = [];
        $globalSequence = 1;

        // Determine wakif name and doa based on prayer_mode
        $wakifName = ($this->prayer_mode === 'semua_donatur') ? $this->nama_donatur : null;
        $doaRequest = ($this->prayer_mode === 'semua_donatur') ? ($this->doa_untuk_semua ?? '') : '';

        // Generate A5 items - use the original database value, not computed
        for ($i = 1; $i <= ($this->attributes['total_a5_count'] ?? 0); $i++) {
            $items[] = [
                'donatur_id' => $this->id,
                'wakaf_type' => 'A5',
                'sequence_in_type' => $i,
                'global_sequence' => $globalSequence++,
                'wakif_name' => $wakifName,
                'doa_request' => $doaRequest,
                'relationship_to_donatur' => 'Diri sendiri',
                'status' => 'pending',
                'created_by' => $this->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Generate A6 items
        for ($i = 1; $i <= ($this->attributes['total_a6_count'] ?? 0); $i++) {
            $items[] = [
                'donatur_id' => $this->id,
                'wakaf_type' => 'A6',
                'sequence_in_type' => $i,
                'global_sequence' => $globalSequence++,
                'wakif_name' => $wakifName,
                'doa_request' => $doaRequest,
                'relationship_to_donatur' => 'Diri sendiri',
                'status' => 'pending',
                'created_by' => $this->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Generate IQRA items
        for ($i = 1; $i <= ($this->attributes['total_iqra_count'] ?? 0); $i++) {
            $items[] = [
                'donatur_id' => $this->id,
                'wakaf_type' => 'IQRA',
                'sequence_in_type' => $i,
                'global_sequence' => $globalSequence++,
                'wakif_name' => $wakifName,
                'doa_request' => $doaRequest,
                'relationship_to_donatur' => 'Diri sendiri',
                'status' => 'pending',
                'created_by' => $this->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $items;
    }
}
