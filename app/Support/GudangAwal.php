<?php

namespace App\Support;

use App\Models\Gudang;
use App\Models\KodeSequence;
use App\Models\Pengiriman;
use Illuminate\Support\Facades\DB;

/**
 * Gudang awal resi yang baru dipesan.
 *
 * Aturan pemilik sistem (05/11/2026): resi dibagi BERGANTIAN PER 1.000 RESI, dari yang
 * terlama — resi ke-1 s.d. 1.000 ke Semarang, 1.001 s.d. 2.000 ke Boyolali, 2.001 s.d.
 * 3.000 ke Semarang lagi, dan seterusnya. Urutan gudang mengikuti id gudang aktif
 * (Semarang lebih dulu).
 *
 * Sebelum aturan ini gudang resi baru terisi saat petugas gudang mulai mengemasnya,
 * sehingga resi tahap pemesanan tidak pernah punya gudang dan kartu "Sedang Dipesan" per
 * gudang di dashboard selalu 0 (di produksi 26.079 dari 26.080 resi pemesanan).
 *
 * Gudang ini MENGUNCI: ia ditulis ke `pengiriman.gudang_id` (lokasi resi), jadi setelah
 * terisi hanya dokumen Perpindahan Mushaf yang boleh mengubahnya.
 */
class GudangAwal
{
    /** Kunci `kode_sequences` yang menyimpan urutan resi terakhir yang dialokasikan. */
    public const KUNCI_URUTAN = 'GUDANG-AWAL-RESI';

    public const UKURAN_BLOK = 1000;

    /** Gudang untuk resi pemesanan berikutnya, atau null bila belum ada gudang aktif. */
    public static function untukResiBerikutnya(): ?int
    {
        $gudangIds = self::urutanGudang();

        if ($gudangIds === []) {
            return null;
        }

        $urutan = KodeSequence::berikutnya(self::KUNCI_URUTAN, fn () => 0);

        return self::gudangUntukUrutan($urutan, $gudangIds);
    }

    /**
     * @param  int  $urutan  urutan resi, mulai dari 1
     * @param  array<int, int>  $gudangIds
     */
    public static function gudangUntukUrutan(int $urutan, array $gudangIds): int
    {
        return $gudangIds[intdiv(max(1, $urutan) - 1, self::UKURAN_BLOK) % count($gudangIds)];
    }

    /** @return array<int, int> */
    public static function urutanGudang(): array
    {
        return Gudang::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Alokasikan resi pemesanan lama yang belum bergudang, dari yang terlama, melanjutkan
     * urutan yang sudah berjalan. Mengembalikan jumlah resi yang dialokasikan.
     */
    public static function alokasikanResiLama(): int
    {
        $gudangIds = self::urutanGudang();

        if ($gudangIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($gudangIds) {
            // Kunci baris urutan dulu supaya resi baru yang dibuat bersamaan menunggu dan
            // melanjutkan dari angka sesudah alokasi ini.
            KodeSequence::query()->insertOrIgnore([
                'kunci' => self::KUNCI_URUTAN,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $penghitung = KodeSequence::query()->where('kunci', self::KUNCI_URUTAN)->lockForUpdate()->firstOrFail();
            $urutan = (int) $penghitung->last_number;

            $ids = Pengiriman::query()
                ->whereNull('gudang_id')
                ->whereHas('status', fn ($q) => $q->where('slug', 'pemesanan'))
                ->orderBy('id')
                ->pluck('id');

            $kelompok = [];
            foreach ($ids as $id) {
                $urutan++;
                $kelompok[self::gudangUntukUrutan($urutan, $gudangIds)][] = $id;
            }

            foreach ($kelompok as $gudangId => $idResi) {
                foreach (array_chunk($idResi, 1000) as $potongan) {
                    Pengiriman::query()->whereIn('id', $potongan)->update(['gudang_id' => $gudangId]);
                }
            }

            $penghitung->update(['last_number' => $urutan]);

            return $ids->count();
        });
    }
}
