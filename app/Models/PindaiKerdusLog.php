<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak satu pindai kerdus oleh sisi distribusi (manager/distribusi).
 *
 * Isinya: kerdus apa, siapa yang memindai, jam berapa, berapa eks mushafnya, dan
 * jenisnya apa (A5/A6/Iqra — satu kerdus selalu satu jenis).
 *
 * Dua sifat yang membentuk seluruh kelas ini:
 *
 *  1. **Hanya catatan.** Tidak ada status pengiriman yang berubah karena pindai di
 *     sini. Kalau kelak pindai ikut menggerakkan status, itu keputusan baru — bukan
 *     perluasan diam-diam dari kelas ini.
 *  2. **Pindai ulang tetap dicatat, tetapi dihitung sekali.** Orang bisa memindai
 *     kerdus yang sama dua kali (label tersentuh dua kali, pindai tidak sengaja,
 *     atau memang diperiksa ulang saat serah terima). Jejaknya semua disimpan,
 *     tetapi ringkasan menghitung tiap kerdus satu kali — kalau tidak, jumlah eks
 *     menggelembung hanya karena satu pindai berlebih.
 */
class PindaiKerdusLog extends Model
{
    protected $table = 'pindai_kerdus_logs';

    /** Dibaca kamera. */
    public const METODE_QR = 'qr';

    /** Diketik petugas (label rusak atau kamera tidak bisa dipakai). */
    public const METODE_MANUAL = 'manual';

    /** Kerdus terdaftar; jenis & jumlah diambil dari sistem. */
    public const ASAL_KERDUS = 'kerdus';

    /** Kerdus belum terdaftar; jenis & jumlah diisi petugas. */
    public const ASAL_MANDIRI = 'mandiri';

    protected $fillable = [
        'user_id',
        'packing_box_id',
        'kode_kerdus',
        'jenis_quran_id',
        'jumlah_mushaf',
        'metode',
        'asal',
        'ulang',
        'catatan',
    ];

    protected $casts = [
        'ulang' => 'boolean',
        'jumlah_mushaf' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // --- Relasi ---

    /**
     * Petugas yang memindai. Nullable: akun yang dihapus tidak menghapus jejaknya.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function packingBox(): BelongsTo
    {
        return $this->belongsTo(PackingBox::class);
    }

    public function jenisQuran(): BelongsTo
    {
        return $this->belongsTo(JenisQuran::class, 'jenis_quran_id');
    }

    // --- Scope ---

    public function scopeTanggal(Builder $query, ?string $tanggal): Builder
    {
        return $query->when($tanggal, fn (Builder $q) => $q->whereDate('created_at', $tanggal));
    }

    // --- Ringkasan ---

    /**
     * Ringkasan pindai: berapa kali memindai, berapa kerdus unik, dan berapa eks
     * mushannya — dipecah per jenis (A5/A6/Iqra).
     *
     * `$tanggal` null berarti seluruh riwayat. Baris kerdus yang dipindai lebih dari
     * sekali dihitung SEKALI, memakai baris TERAKHIR: isi kerdus bisa bertambah di
     * gudang setelah pindai pertama, dan angka terakhir yang paling dekat dengan
     * kenyataan saat kerdus diserahkan.
     *
     * @return array{pindai: int, kerdus: int, mushaf: int, per_jenis: array<int, array{kode: string|null, nama: string, kerdus: int, mushaf: int}>}
     */
    public static function ringkasan(?string $tanggal = null): array
    {
        $idTerakhir = static::query()
            ->selectRaw('MAX(id) as id')
            ->tanggal($tanggal)
            ->groupBy('kode_kerdus');

        $perJenis = static::query()
            ->whereIn('id', $idTerakhir)
            ->selectRaw('jenis_quran_id, COUNT(*) as kerdus, COALESCE(SUM(jumlah_mushaf), 0) as mushaf')
            ->groupBy('jenis_quran_id')
            ->get()
            ->keyBy(fn (self $baris) => (int) ($baris->jenis_quran_id ?? 0));

        $jenis = JenisQuran::query()
            ->orderBy('id')
            ->get(['id', 'kode_jenis', 'nama_jenis', 'is_active']);

        $perJenisRapi = $jenis
            ->filter(fn (JenisQuran $j) => $j->is_active || $perJenis->has($j->id))
            ->map(fn (JenisQuran $j) => [
                'kode' => $j->kode_jenis,
                'nama' => $j->nama_jenis,
                'kerdus' => (int) ($perJenis[$j->id]->kerdus ?? 0),
                'mushaf' => (int) ($perJenis[$j->id]->mushaf ?? 0),
            ])
            ->values();

        // Kerdus tanpa jenis (baru dibuat, belum diisi) tetap dilaporkan; kalau tidak,
        // jumlah per jenis tidak akan pernah sama dengan jumlah keseluruhan dan
        // selisihnya tak bisa dijelaskan dari layar.
        if ($perJenis->has(0)) {
            $perJenisRapi->push([
                'kode' => null,
                'nama' => 'Tanpa jenis',
                'kerdus' => (int) $perJenis[0]->kerdus,
                'mushaf' => (int) $perJenis[0]->mushaf,
            ]);
        }

        return [
            'pindai' => static::query()->tanggal($tanggal)->count(),
            'kerdus' => (int) $perJenisRapi->sum('kerdus'),
            'mushaf' => (int) $perJenisRapi->sum('mushaf'),
            'per_jenis' => $perJenisRapi->all(),
        ];
    }
}
