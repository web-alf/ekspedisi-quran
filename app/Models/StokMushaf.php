<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokMushaf extends Model
{
    protected $table = 'stok_mushaf';

    protected $fillable = ['gudang_id', 'jenis_quran_id', 'terjemahan', 'jumlah_eks'];

    protected function casts(): array
    {
        return [
            'jumlah_eks' => 'integer',
            // Temuan lapangan 19/10/2026: A5/A6 ada varian ber-terjemahan dan tanpa.
            'terjemahan' => 'boolean',
        ];
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function jenisQuran(): BelongsTo
    {
        return $this->belongsTo(JenisQuran::class);
    }

    public function pergerakan(): HasMany
    {
        return $this->hasMany(StokMushafPergerakan::class);
    }

    /**
     * Satuan dasar adalah EKS (jumlah_eks). DUS hanya tampilan penjumlahan:
     * 1 dus = kapasitas jenis (1 dus A5 = 20 eks, A6 = 40, IQRO = 160).
     */
    public function jumlahEks(): int
    {
        return $this->jumlah_eks;
    }

    public function jumlahDus(): int
    {
        $kapasitas = $this->jenisQuran->getDefaultCapacity();

        return intdiv($this->jumlah_eks, $kapasitas);
    }

    public function sisaEksDiLuarDus(): int
    {
        return $this->jumlah_eks % $this->jenisQuran->getDefaultCapacity();
    }
}
