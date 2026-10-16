<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumen perpindahan mushaf ber-resi antar gudang.
 *
 * Polanya mengikuti `transfer_stok` (stok tanpa muwakif) dengan satu perbedaan penting:
 * satuannya RESI, bukan eks. Alasannya, mushaf wakaf punya identitas (wakif, doa, tujuan
 * kirim) dan identitas itu menempel pada resi — memindahkan "20 eks" tanpa menyebut resi
 * mana saja membuat lokasinya tidak bisa ditelusuri lagi.
 *
 * Lokasi mushaf (`pengiriman.gudang_id`) HANYA berubah saat dokumen DITERIMA gudang
 * tujuan. Kalau berubah saat dibuat, mushaf yang masih di jalan sudah terhitung di gudang
 * tujuan — dan kalau dokumennya batal, catatannya sudah terlanjur bohong.
 */
class TransferMushaf extends Model
{
    use HasFactory;

    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_DITERIMA = 'diterima';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $table = 'transfer_mushaf';

    protected $fillable = [
        'kode',
        'gudang_asal_id',
        'gudang_tujuan_id',
        'status',
        'jumlah_resi',
        'jumlah_eks',
        'catatan',
        'created_by',
        'received_by',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'jumlah_resi' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(TransferMushafItem::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function sudahSelesai(): bool
    {
        return in_array($this->status, [self::STATUS_DITERIMA, self::STATUS_DIBATALKAN], true);
    }

    /**
     * Kode dokumen: TM-YYYYMMDD-NNN.
     *
     * Nomor urut dihitung dari dokumen HARI ITU, bukan dari id, supaya kode tetap pendek
     * dan bisa dibacakan lewat telepon. Pemeriksaan keunikan dilakukan di dalam transaksi
     * pemanggil (dokumen dibuat di dalam DB::transaction + lockForUpdate).
     */
    public static function generateKode(): string
    {
        $tanggal = now()->format('Ymd');
        $urut = static::where('kode', 'like', "TM-{$tanggal}-%")->lockForUpdate()->count() + 1;

        return sprintf('TM-%s-%03d', $tanggal, $urut);
    }
}
