<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\KodeSequence;
use App\Models\StokMushaf;
use App\Models\StokMushafPergerakan;
use App\Models\TransferStok;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stok mushaf tanpa muwakif (Fase 3 rombakan gudang).
 *
 * Aturan pemilik sistem:
 * - mushaf bisa jadi STOK gudang dulu tanpa donatur, diinput manual
 *   manager/SPV ("N dus jenis X di gudang Y"), dipotong saat muwakif
 *   dilampirkan;
 * - perpindahan antar gudang lewat DOKUMEN TRANSFER: dibuat lalu DITERIMA SPV
 *   gudang tujuan — stok baru berpindah saat diterima, bukan saat dibuat
 *   (barang fisik belum pasti tiba);
 * - seluruh pergerakan dicatat di buku besar beserta pelakunya, supaya "stok
 *   habis" bisa diaudit.
 *
 * Dus di sini = doz (BoxUnits): 1 dus A5 = 20 eks, A6 = 40, IQRO = 160.
 */
class StokMushafService
{
    public function catatMasuk(Gudang $gudang, JenisQuran $jenis, int $jumlahEks, bool $terjemahan, ?string $catatan, User $pelaku): StokMushaf
    {
        $jumlahEks = $this->jumlahBulan($jumlahEks);

        return DB::transaction(function () use ($gudang, $jenis, $jumlahEks, $terjemahan, $catatan, $pelaku) {
            $stok = $this->kunciStok($gudang->id, $jenis->id, $terjemahan);

            $stok->increment('jumlah_eks', $jumlahEks);
            $stok->refresh();

            $this->catatPergerakan($gudang->id, $jenis->id, $terjemahan, 'masuk', $jumlahEks, $catatan, $pelaku);

            return $stok;
        });
    }

    public function keluarkan(Gudang $gudang, JenisQuran $jenis, int $jumlahEks, bool $terjemahan, ?string $catatan, User $pelaku): StokMushaf
    {
        $jumlahEks = $this->jumlahBulan($jumlahEks);

        return DB::transaction(function () use ($gudang, $jenis, $jumlahEks, $terjemahan, $catatan, $pelaku) {
            $stok = $this->kunciStok($gudang->id, $jenis->id, $terjemahan);

            if ($stok->jumlah_eks < $jumlahEks) {
                throw new \Exception(sprintf(
                    'Stok tidak cukup di %s untuk %s%s: ada %d eks, mau keluar %d eks.',
                    $gudang->nama, $jenis->nama_jenis, $this->sebutanVarian($terjemahan), $stok->jumlah_eks, $jumlahEks,
                ));
            }

            $stok->decrement('jumlah_eks', $jumlahEks);
            $stok->refresh();

            $this->catatPergerakan($gudang->id, $jenis->id, $terjemahan, 'keluar', $jumlahEks, $catatan, $pelaku);

            return $stok;
        });
    }

    public function buatTransfer(Gudang $asal, Gudang $tujuan, JenisQuran $jenis, int $jumlahEks, bool $terjemahan, ?string $catatan, User $pembuat): TransferStok
    {
        if ($asal->id === $tujuan->id) {
            throw new \Exception('Asal dan tujuan transfer tidak boleh gudang yang sama.');
        }

        $jumlahEks = $this->jumlahBulan($jumlahEks);

        return DB::transaction(function () use ($asal, $tujuan, $jenis, $jumlahEks, $terjemahan, $catatan, $pembuat) {
            $stokAsal = $this->kunciStok($asal->id, $jenis->id, $terjemahan);

            if ($stokAsal->jumlah_eks < $jumlahEks) {
                throw new \Exception(sprintf(
                    'Stok tidak cukup di %s untuk %s%s: ada %d eks, mau dialihkan %d eks.',
                    $asal->nama, $jenis->nama_jenis, $this->sebutanVarian($terjemahan), $stokAsal->jumlah_eks, $jumlahEks,
                ));
            }

            return TransferStok::create([
                'kode' => $this->kodeBaru(),
                'gudang_asal_id' => $asal->id,
                'gudang_tujuan_id' => $tujuan->id,
                'jenis_quran_id' => $jenis->id,
                'terjemahan' => $terjemahan,
                'jumlah_eks' => $jumlahEks,
                'status' => 'diproses',
                'catatan' => $catatan,
                'created_by' => $pembuat->id,
            ]);
            // Stok TIDAK dipindah di sini — baru berpindah saat diterima.
        });
    }

    public function terimaTransfer(TransferStok $transfer, User $pelaku): void
    {
        DB::transaction(function () use ($transfer, $pelaku) {
            // Status dicek ulang pada baris yang DIKUNCI, bukan pada instance hasil
            // route binding: dua klik "Terima" (atau SPV dan Manager bersamaan)
            // sama-sama membawa instance berstatus 'diproses', sehingga tanpa ini
            // stok berpindah dua kali.
            $terkunci = $this->kunciTransferDiproses($transfer, fn (string $status) => 'Dokumen transfer ini sudah '.($status === 'diterima' ? 'diterima' : 'dibatalkan').'.');

            $asal = $this->kunciStok($terkunci->gudang_asal_id, $terkunci->jenis_quran_id, (bool) $terkunci->terjemahan);

            if ($asal->jumlah_eks < $terkunci->jumlah_eks) {
                throw new \Exception('Stok di gudang asal sudah makin berkurang; transfer tidak bisa diterima sebesar itu.');
            }

            $asal->decrement('jumlah_eks', $terkunci->jumlah_eks);
            $tujuan = $this->kunciStok($terkunci->gudang_tujuan_id, $terkunci->jenis_quran_id, (bool) $terkunci->terjemahan);
            $tujuan->increment('jumlah_eks', $terkunci->jumlah_eks);

            $this->catatPergerakan($terkunci->gudang_asal_id, $terkunci->jenis_quran_id, (bool) $terkunci->terjemahan, 'transfer_keluar', $terkunci->jumlah_eks, 'Transfer '.$terkunci->kode, $pelaku, $terkunci->id);
            $this->catatPergerakan($terkunci->gudang_tujuan_id, $terkunci->jenis_quran_id, (bool) $terkunci->terjemahan, 'transfer_masuk', $terkunci->jumlah_eks, 'Transfer '.$terkunci->kode, $pelaku, $terkunci->id);

            $terkunci->update([
                'status' => 'diterima',
                'approved_by' => $pelaku->id,
                'approved_at' => now(),
            ]);
        });

        $transfer->refresh();
    }

    public function batalkanTransfer(TransferStok $transfer, User $pelaku): void
    {
        DB::transaction(function () use ($transfer, $pelaku) {
            // Dikunci agar pembatalan tidak menimpa dokumen yang baru saja diterima
            // (stok sudah pindah tetapi dokumen tercatat "dibatalkan").
            $terkunci = $this->kunciTransferDiproses($transfer, fn () => 'Hanya dokumen yang masih "diproses" yang bisa dibatalkan.');

            $terkunci->update([
                'status' => 'dibatalkan',
                'approved_by' => $pelaku->id,
                'approved_at' => now(),
            ]);
        });

        $transfer->refresh();
    }

    /**
     * Ambil ulang dokumen transfer dengan kunci baris dan pastikan masih 'diproses'.
     *
     * @param  \Closure(string): string  $pesanGagal
     */
    private function kunciTransferDiproses(TransferStok $transfer, \Closure $pesanGagal): TransferStok
    {
        $terkunci = TransferStok::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();

        if ($terkunci->status !== 'diproses') {
            throw new \Exception($pesanGagal($terkunci->status));
        }

        return $terkunci;
    }

    private function kunciStok(int $gudangId, int $jenisId, bool $terjemahan): StokMushaf
    {
        return StokMushaf::where('gudang_id', $gudangId)
            ->where('jenis_quran_id', $jenisId)
            ->where('terjemahan', $terjemahan)
            ->lockForUpdate()
            ->first()
            ?? StokMushaf::create([
                'gudang_id' => $gudangId,
                'jenis_quran_id' => $jenisId,
                'terjemahan' => $terjemahan,
                'jumlah_eks' => 0,
            ]);
    }

    private function catatPergerakan(int $gudangId, int $jenisId, bool $terjemahan, string $tipe, int $jumlahEks, ?string $catatan, User $pelaku, ?int $transferId = null): void
    {
        StokMushafPergerakan::create([
            'gudang_id' => $gudangId,
            'jenis_quran_id' => $jenisId,
            'terjemahan' => $terjemahan,
            'tipe' => $tipe,
            'jumlah_eks' => $jumlahEks,
            'catatan' => $catatan,
            'created_by' => $pelaku->id,
            'transfer_id' => $transferId,
        ]);
    }

    /**
     * Baris stok ringkas untuk form dokumen transfer (tab "Stok tanpa muwakif" di halaman
     * Perpindahan Mushaf, 26/10/2026): satu baris per gudang × jenis × varian, dengan sisa
     * eks supaya orang yang membuat dokumen melihat stok yang benar-benar ada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ringkasanStok(User $user): array
    {
        return StokMushaf::query()
            ->with(['gudang:id,nama', 'jenisQuran:id,kode_jenis,nama_jenis,default_capacity'])
            ->when($user->hasRole(RoleEnum::SPV_GUDANG->value), fn ($q) => $q->where('gudang_id', $user->gudang_id))
            ->orderBy('gudang_id')->orderBy('jenis_quran_id')->get()
            ->map(fn (StokMushaf $stok) => [
                'gudang_id' => $stok->gudang_id,
                'gudang' => $stok->gudang?->nama,
                'jenis_id' => $stok->jenis_quran_id,
                'kode_jenis' => $stok->jenisQuran?->kode_jenis,
                'jenis' => $stok->jenisQuran?->nama_jenis,
                'kapasitas' => (int) ($stok->jenisQuran?->default_capacity ?? 0),
                'terjemahan' => (bool) $stok->terjemahan,
                'varian' => $stok->terjemahan ? 'Ada terjemahan' : 'Tanpa terjemahan',
                'eks' => (int) $stok->jumlah_eks,
            ])->values()->all();
    }

    /**
     * Dokumen transfer stok terakhir — satu sumber untuk halaman Stok Mushaf dan tab
     * "Stok tanpa muwakif" di halaman Perpindahan Mushaf, supaya kedua layar menampilkan
     * daftar yang sama (dulu daftar ini hanya ada di halaman stok).
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftarTransfer(User $user, int $limit = 50): array
    {
        return TransferStok::query()
            ->with(['gudangAsal:id,nama', 'gudangTujuan:id,nama', 'jenisQuran:id,nama_jenis', 'pembuat:id,name'])
            ->when($user->hasRole(RoleEnum::SPV_GUDANG->value), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('gudang_asal_id', $user->gudang_id)
                ->orWhere('gudang_tujuan_id', $user->gudang_id)))
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn (TransferStok $t) => [
                'id' => $t->id,
                'kode' => $t->kode,
                'asal_id' => $t->gudang_asal_id,
                'asal' => $t->gudangAsal?->nama,
                'tujuan_id' => $t->gudang_tujuan_id,
                'tujuan' => $t->gudangTujuan?->nama,
                'jenis' => $t->jenisQuran?->nama_jenis,
                'terjemahan' => $t->terjemahan ? 'Ada terjemahan' : 'Tanpa terjemahan',
                'jumlah_eks' => (int) $t->jumlah_eks,
                'status' => $t->status,
                'catatan' => $t->catatan,
                'dibuat_oleh' => $t->pembuat?->name,
                'dibuat_oleh_id' => $t->created_by,
                'dibuat_pada' => $t->created_at?->format('d/m/Y H:i'),
            ])->values()->all();
    }

    /**
     * Sebutan varian untuk pesan galat: stok A5 ber-terjemahan dan tanpa terjemahan adalah
     * dua tumpukan terpisah, jadi "stok tidak cukup" harus menyebut yang mana.
     */
    private function sebutanVarian(bool $terjemahan): string
    {
        return $terjemahan ? ' (ada terjemahan)' : ' (tanpa terjemahan)';
    }

    /**
     * Kode TS-YYYYMMDD-NNN dari KodeSequence yang dikunci. Dulu `count() + 1`:
     * dua transfer dari gudang asal berbeda pada saat bersamaan mendapat kode
     * sama dan salah satunya gagal kena unique index.
     */
    private function kodeBaru(): string
    {
        $prefix = 'TS-'.today()->format('Ymd').'-';

        $urutan = KodeSequence::berikutnya($prefix, fn () => TransferStok::whereDate('created_at', today())->count());

        return $prefix.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }

    private function jumlahBulan(int $jumlah): int
    {
        return max(1, $jumlah);
    }
}
