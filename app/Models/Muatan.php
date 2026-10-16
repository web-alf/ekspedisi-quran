<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Muatan — sekumpulan resi yang diantar SATU kurir dalam satu perjalanan.
 *
 * Status perjalanan TIDAK disimpan di sini, melainkan dibaca dari status
 * resi-resi di dalamnya. Kalau muatan punya status sendiri, ia bisa berbeda dari
 * kenyataan resinya dan tidak ada lagi sumber kebenaran tunggal — penyebab
 * klasik "status muatan bilang terkirim, resinya belum".
 */
class Muatan extends Model
{
    use HasFactory;

    protected $table = 'muatan';

    protected $fillable = [
        'kode_muatan',
        'kurir_id',
        'created_by',
        'tanggal_muatan',
        'nama_muatan',
        'catatan',
        'jumlah_lembaga',
        'total_resi',
        'total_mushaf',
    ];

    protected $casts = [
        'tanggal_muatan' => 'date',
        'jumlah_lembaga' => 'integer',
        'total_resi' => 'integer',
        'total_mushaf' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->kode_muatan)) {
                $model->kode_muatan = $model->generateUniqueKodeMuatan();
            }
        });
    }

    /**
     * Nomor muatan unik: MUK-YYYY-XXXXX.
     *
     * Memakai NoResiSequence — mekanisme yang sama dengan nomor resi (transaksi +
     * lockForUpdate), sehingga dua muatan tidak mungkin dapat nomor yang sama
     * walau dibuat bersamaan.
     */
    public function generateUniqueKodeMuatan(): string
    {
        $tahun = (int) date('Y');
        $nomor = NoResiSequence::getNextNumber($tahun);

        return "MUK-{$tahun}-".str_pad((string) $nomor, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Apakah format kode muatan sah.
     */
    public function isValidKodeMuatanFormat(?string $kode = null): bool
    {
        return (bool) preg_match('/^MUK-\d{4}-\d{5}$/', $kode ?: (string) $this->kode_muatan);
    }

    // --- Relationships ---

    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MuatanItem::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * Resi yang ada di dalam muatan ini.
     */
    public function pengiriman()
    {
        return $this->hasManyThrough(
            Pengiriman::class,
            MuatanItem::class,
            'muatan_id',      // foreign key on muatan_items
            'id',             // foreign key on pengiriman
            'id',             // local key on muatan
            'pengiriman_id'   // local key on muatan_items
        );
    }

    // --- Ringkasan status ---

    /**
     * Hitung ulang total_resi & total_mushaf dari isi muatan.
     *
     * Dipanggil setiap kali isi muatan berubah supaya angka di daftar tidak
     * melenceng dari kenyataan — 26 ribu resi terlalu banyak untuk dihitung
     * ulang tiap render daftar.
     */
    public function syncTotals(): void
    {
        // SATU pernyataan UPDATE, bukan "hitung lalu tulis": subquery di dalam UPDATE
        // dibaca sebagai pembacaan berkunci (data terbaru), sedangkan SELECT biasa di
        // dalam transaksi membaca snapshot. Dulu dua pindai bersamaan ke satu muatan
        // masing-masing menghitung "1" dan total akhirnya 1 padahal isinya 2 — lalu
        // daftar muatan menganggapnya selesai saat baru separuh resinya diterima.
        $tabel = $this->getTable();

        DB::update(
            "UPDATE {$tabel} SET
                total_resi = (SELECT COUNT(*) FROM muatan_items WHERE muatan_items.muatan_id = {$tabel}.id),
                total_mushaf = (
                    SELECT COALESCE(SUM(pengiriman.jumlah_quran), 0)
                    FROM muatan_items
                    JOIN pengiriman ON pengiriman.id = muatan_items.pengiriman_id
                    WHERE muatan_items.muatan_id = {$tabel}.id
                ),
                updated_at = ?
            WHERE id = ?",
            [now(), $this->getKey()]
        );

        $terkini = static::query()->whereKey($this->getKey())->lockForUpdate()->first(['id', 'total_resi', 'total_mushaf', 'updated_at']);

        if ($terkini) {
            $kolom = ['total_resi', 'total_mushaf', 'updated_at'];
            $this->forceFill($terkini->only($kolom))->syncOriginalAttributes($kolom);
        }
    }

    /**
     * Sebaran status resi di dalam muatan ini.
     *
     * @return array<string, int> slug status => jumlah
     */
    public function sebaranStatus(): array
    {
        return DB::table('muatan_items')
            ->join('pengiriman', 'pengiriman.id', '=', 'muatan_items.pengiriman_id')
            ->join('status_pengiriman', 'status_pengiriman.id', '=', 'pengiriman.status_id')
            ->where('muatan_items.muatan_id', $this->id)
            ->groupBy('status_pengiriman.slug')
            ->pluck(DB::raw('COUNT(*)'), 'status_pengiriman.slug')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Apakah SELURUH resi dalam muatan sudah berstatus diterima.
     *
     * Jumlah resi dihitung dari ISI MUATAN, bukan dari kolom ringkasan
     * `total_resi`. Kolom itu bisa basi — mis. bila isi diubah lewat jalur yang
     * tidak memanggil syncTotals() — dan keputusan "selesai" tidak boleh
     * bergantung pada angka yang bisa meleset. Salah di sini berarti distribusi
     * tampak selesai padahal barangnya belum diterima.
     *
     * Muatan kosong dianggap belum selesai: muatan yang isinya baru dikosongkan
     * tidak boleh tiba-tiba tampak selesai.
     */
    public function getSelesaiAttribute(): bool
    {
        $jumlahResi = $this->items()->count();

        if ($jumlahResi === 0) {
            return false;
        }

        return ($this->sebaranStatus()['diterima'] ?? 0) === $jumlahResi;
    }

    // --- Scopes ---

    public function scopeForKurir($query, ?int $kurirId)
    {
        return $query->where('kurir_id', $kurirId);
    }

    public function scopeTanggal($query, string $tanggal)
    {
        return $query->whereDate('tanggal_muatan', $tanggal);
    }
}
