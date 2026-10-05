<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu resi di dalam sebuah muatan.
 *
 * `pengiriman_id` unik di tingkat basis data: satu resi hanya boleh berada di
 * satu muatan. Tanpa batasan itu, satu barang bisa muncul di dua perjalanan
 * sekaligus dan dua kurir mengantar barang yang sama.
 */
class MuatanItem extends Model
{
    use HasFactory;

    protected $table = 'muatan_items';

    protected $fillable = [
        'muatan_id',
        'pengiriman_id',
        'urutan',
        'dimuat_at',
        'dimuat_by',
        'catatan',
    ];

    protected $casts = [
        'dimuat_at' => 'datetime',
        'urutan' => 'integer',
    ];

    public function muatan(): BelongsTo
    {
        return $this->belongsTo(Muatan::class);
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }

    public function pemuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dimuat_by');
    }
}
