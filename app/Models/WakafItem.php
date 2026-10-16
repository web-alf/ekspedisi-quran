<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WakafItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'donatur_id',
        'pengiriman_id',
        'wakaf_type',
        'sequence_in_type',
        'global_sequence',
        'wakif_name',
        'doa_request',
        'relationship_to_donatur',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'sequence_in_type' => 'integer',
        'global_sequence' => 'integer',
    ];

    // Relationships
    public function donatur(): BelongsTo
    {
        return $this->belongsTo(Donatur::class);
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors
    public function getWakafTypeNameAttribute(): string
    {
        return match ($this->wakaf_type) {
            'A5' => 'Mushaf Al-Qur\'an A5',
            'A6' => 'Mushaf Al-Qur\'an A6',
            'IQRA' => 'IQRA',
            default => $this->wakaf_type
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu Proses',
            'processed' => 'Sudah Diproses',
            'shipped' => 'Dikirim',
            'delivered' => 'Diterima',
            default => $this->status
        };
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->wakaf_type_name} #{$this->global_sequence} - {$this->wakif_name}";
    }

    // Scopes
    public function scopeByDonatur($query, $donaturId)
    {
        return $query->where('donatur_id', $donaturId);
    }

    public function scopeByWakafType($query, $type)
    {
        return $query->where('wakaf_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->whereIn('status', ['processed', 'shipped', 'delivered']);
    }

    // Helper methods
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['pending']);
    }

    public function canBeDeleted(): bool
    {
        // Item must be pending
        if ($this->status !== 'pending') {
            return false;
        }

        // If pengiriman exists, check its status
        if ($this->pengiriman) {
            $pengirimanStatus = $this->pengiriman->status->nama ?? 'Unknown';
            $deletableStatuses = ['Pending', 'Proses Pemesanan', 'Batal'];

            return in_array($pengirimanStatus, $deletableStatuses);
        }

        // If no pengiriman, it can be deleted
        return true;
    }

    public function markAsProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    public function markAsShipped(): void
    {
        $this->update(['status' => 'shipped']);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }
}
