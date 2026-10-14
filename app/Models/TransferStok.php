<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferStok extends Model
{
    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_DITERIMA = 'diterima';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $table = 'transfer_stok';

    protected $fillable = [
        'kode',
        'gudang_asal_id',
        'gudang_tujuan_id',
        'jenis_quran_id',
        'jumlah_eks',
        'status',
        'catatan',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'jumlah_eks' => 'integer',
        ];
    }

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function jenisQuran(): BelongsTo
    {
        return $this->belongsTo(JenisQuran::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
