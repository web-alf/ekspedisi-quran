<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\JenisQuran;
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
    public function catatMasuk(Gudang $gudang, JenisQuran $jenis, int $jumlahEks, ?string $catatan, User $pelaku): StokMushaf
    {
        $jumlahEks = $this->jumlahBulan($jumlahEks);

        return DB::transaction(function () use ($gudang, $jenis, $jumlahEks, $catatan, $pelaku) {
            $stok = $this->kunciStok($gudang->id, $jenis->id);

            $stok->increment('jumlah_eks', $jumlahEks);
            $stok->refresh();

            $this->catatPergerakan($gudang->id, $jenis->id, 'masuk', $jumlahEks, $catatan, $pelaku);

            return $stok;
        });
    }

    public function keluarkan(Gudang $gudang, JenisQuran $jenis, int $jumlahEks, ?string $catatan, User $pelaku): StokMushaf
    {
        $jumlahEks = $this->jumlahBulan($jumlahEks);

        return DB::transaction(function () use ($gudang, $jenis, $jumlahEks, $catatan, $pelaku) {
            $stok = $this->kunciStok($gudang->id, $jenis->id);

            if ($stok->jumlah_eks < $jumlahEks) {
                throw new \Exception(sprintf(
                    'Stok tidak cukup di %s untuk %s: ada %d eks, mau keluar %d eks.',
                    $gudang->nama, $jenis->nama_jenis, $stok->jumlah_eks, $jumlahEks,
                ));
            }

            $stok->decrement('jumlah_eks', $jumlahEks);
            $stok->refresh();

            $this->catatPergerakan($gudang->id, $jenis->id, 'keluar', $jumlahEks, $catatan, $pelaku);

            return $stok;
        });
    }

    public function buatTransfer(Gudang $asal, Gudang $tujuan, JenisQuran $jenis, int $jumlahEks, ?string $catatan, User $pembuat): TransferStok
    {
        if ($asal->id === $tujuan->id) {
            throw new \Exception('Asal dan tujuan transfer tidak boleh gudang yang sama.');
        }

        $jumlahEks = $this->jumlahBulan($jumlahEks);

        return DB::transaction(function () use ($asal, $tujuan, $jenis, $jumlahEks, $catatan, $pembuat) {
            $stokAsal = $this->kunciStok($asal->id, $jenis->id);

            if ($stokAsal->jumlah_eks < $jumlahEks) {
                throw new \Exception(sprintf(
                    'Stok tidak cukup di %s untuk %s: ada %d eks, mau dialihkan %d eks.',
                    $asal->nama, $jenis->nama_jenis, $stokAsal->jumlah_eks, $jumlahEks,
                ));
            }

            return TransferStok::create([
                'kode' => $this->kodeBaru(),
                'gudang_asal_id' => $asal->id,
                'gudang_tujuan_id' => $tujuan->id,
                'jenis_quran_id' => $jenis->id,
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
        if ($transfer->status !== 'diproses') {
            throw new \Exception('Dokumen transfer ini sudah '.($transfer->status === 'diterima' ? 'diterima' : 'dibatalkan').'.');
        }

        DB::transaction(function () use ($transfer, $pelaku) {
            $asal = $this->kunciStok($transfer->gudang_asal_id, $transfer->jenis_quran_id);

            if ($asal->jumlah_eks < $transfer->jumlah_eks) {
                throw new \Exception('Stok di gudang asal sudah makin berkurang; transfer tidak bisa diterima sebesar itu.');
            }

            $asal->decrement('jumlah_eks', $transfer->jumlah_eks);
            $tujuan = $this->kunciStok($transfer->gudang_tujuan_id, $transfer->jenis_quran_id);
            $tujuan->increment('jumlah_eks', $transfer->jumlah_eks);

            $this->catatPergerakan($transfer->gudang_asal_id, $transfer->jenis_quran_id, 'transfer_keluar', $transfer->jumlah_eks, 'Transfer '.$transfer->kode, $pelaku, $transfer->id);
            $this->catatPergerakan($transfer->gudang_tujuan_id, $transfer->jenis_quran_id, 'transfer_masuk', $transfer->jumlah_eks, 'Transfer '.$transfer->kode, $pelaku, $transfer->id);

            $transfer->update([
                'status' => 'diterima',
                'approved_by' => $pelaku->id,
                'approved_at' => now(),
            ]);
        });
    }

    public function batalkanTransfer(TransferStok $transfer, User $pelaku): void
    {
        if ($transfer->status !== 'diproses') {
            throw new \Exception('Hanya dokumen yang masih "diproses" yang bisa dibatalkan.');
        }

        $transfer->update([
            'status' => 'dibatalkan',
            'approved_by' => $pelaku->id,
            'approved_at' => now(),
        ]);
    }

    private function kunciStok(int $gudangId, int $jenisId): StokMushaf
    {
        return StokMushaf::where('gudang_id', $gudangId)
            ->where('jenis_quran_id', $jenisId)
            ->lockForUpdate()
            ->first()
            ?? StokMushaf::create(['gudang_id' => $gudangId, 'jenis_quran_id' => $jenisId, 'jumlah_eks' => 0]);
    }

    private function catatPergerakan(int $gudangId, int $jenisId, string $tipe, int $jumlahEks, ?string $catatan, User $pelaku, ?int $transferId = null): void
    {
        StokMushafPergerakan::create([
            'gudang_id' => $gudangId,
            'jenis_quran_id' => $jenisId,
            'tipe' => $tipe,
            'jumlah_eks' => $jumlahEks,
            'catatan' => $catatan,
            'created_by' => $pelaku->id,
            'transfer_id' => $transferId,
        ]);
    }

    private function kodeBaru(): string
    {
        $urutan = TransferStok::whereDate('created_at', today())->count() + 1;

        return 'TS-'.today()->format('Ymd').'-'.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }

    private function jumlahBulan(int $jumlah): int
    {
        return max(1, $jumlah);
    }
}
