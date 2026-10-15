<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu RESI yang dipindahkan dalam sebuah dokumen perpindahan mushaf.
 *
 * `pengiriman_id` diberi kunci unik di basis data: satu mushaf tidak boleh berada di dua
 * dokumen perpindahan sekaligus — kalau boleh, dua gudang bisa menerimanya dan lokasinya
 * menjadi tidak bisa ditentukan.
 */
class TransferMushafItem extends Model
{
    protected $table = 'transfer_mushaf_items';

    protected $fillable = ['transfer_mushaf_id', 'pengiriman_id'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(TransferMushaf::class, 'transfer_mushaf_id');
    }

    public function pengiriman(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class);
    }
}
