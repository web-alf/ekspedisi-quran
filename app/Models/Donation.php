<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    use HasFactory;

    protected $table = 'donations';

    protected $fillable = [
        'donatur_id',
        'jenis_donasi',
        'jumlah_donasi',
        'tanggal_donasi',
        'metode_pembayaran',
        'status_donasi',
        'keterangan',
        'bukti_transfer',
        'confirmed_at',
        'confirmed_by'
    ];

    protected $casts = [
        'tanggal_donasi' => 'date',
        'confirmed_at' => 'datetime',
        'jumlah_donasi' => 'decimal:2'
    ];

    /**
     * Get the donatur that owns the donation
     */
    public function donatur(): BelongsTo
    {
        return $this->belongsTo(Donatur::class);
    }

    /**
     * Get the user who confirmed this donation
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get all wakaf items related to this donation
     */
    public function wakafItems(): HasMany
    {
        return $this->hasMany(WakafItem::class);
    }

    /**
     * Get all pengiriman related to this donation
     */
    public function pengiriman(): HasMany
    {
        return $this->hasMany(Pengiriman::class);
    }

    /**
     * Scope for confirmed donations
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status_donasi', 'confirmed');
    }

    /**
     * Scope for pending donations
     */
    public function scopePending($query)
    {
        return $query->where('status_donasi', 'pending');
    }

    /**
     * Get donation amount formatted
     */
    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->jumlah_donasi, 0, ',', '.');
    }
}