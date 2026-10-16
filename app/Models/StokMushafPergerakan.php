<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buku besar stok: tiap masuk/keluar/transfer tercatat siapa pelakunya.
 * Tanpa ini "stok habis" tak bisa diaudit.
 */
class StokMushafPergerakan extends Model
{
    protected $table = 'stok_mushaf_pergerakan';

    protected $fillable = [
        'gudang_id',
        'jenis_quran_id',
        'tipe',
        'jumlah_eks',
        'catatan',
        'created_by',
        'transfer_id',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function jenisQuran(): BelongsTo
    {
        return $this->belongsTo(JenisQuran::class);
    }

    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(TransferStok::class);
    }
}
