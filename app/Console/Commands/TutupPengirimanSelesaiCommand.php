<?php

namespace App\Console\Commands;

use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\WakafBatch;
use App\Services\ConsolidatedCertificateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menutup tahap akhir distribusi: batch yang seluruh resinya sudah diterima
 * ditandai selesai, dan sertifikat donaturnya dibuatkan catatannya.
 *
 * Kenapa perlu: alur berhenti di "diterima". Batch yang resinya sudah sampai
 * semua tidak pernah ditutup — di produksi 15.552 batch menggantung dan 0
 * sertifikat, karena rantai ke hulu putus: resi tidak tertaut ke batch, sehingga
 * WakafBatch::updateStatus() selalu menyimpulkan "belum ada pengiriman".
 *
 * Kenapa penanda idempotennya KOLOM SERTIFIKAT, bukan status batch:
 * PengirimanObserver memanggil WakafBatch::updateStatus() setiap kali status
 * resi berubah, dan begitu resi terakhir menjadi "diterima" batch LANGSUNG
 * berstatus completed. Kalau perintah ini menyaring `status != completed`, batch
 * normal justru tidak akan pernah terlihat dan sertifikatnya tidak akan pernah
 * dibuat — persis kegagalan yang mau diperbaiki. Karena itu:
 *   - penyaring batch tidak melihat status batch sama sekali, hanya "semua resi diterima";
 *   - selesainya dipulihkan dari kolom `sertifikat_generated`, bukan dari status.
 *
 * Kenapa perintah terjadwal, bukan hook saat status berubah: notifikasi ke
 * donatur TIDAK BISA dikerjakan — fitur WhatsApp dihapus permanen (migrasi
 * 2025_08_15_190504 menghapus 4 tabel + 10 izin, `down()`-nya sengaja dibuat tidak
 * bisa dibatalkan), dan job yang dirujuk kode lama (`SendDeliveryNotificationToWakif`,
 * `SendCertificateToWakif`) tidak pernah ada. Yang bisa dikerjakan sekarang:
 * penutupan batch + catatan sertifikat. Aman dijalankan berulang.
 */
class TutupPengirimanSelesaiCommand extends Command
{
    protected $signature = 'wakaf:tutup-pengiriman
                          {--limit=200 : Jumlah batch yang diperiksa sekali jalan}
                          {--kering : Hanya tampilkan rencananya, tidak mengubah apa pun}';

    protected $description = 'Tutup batch yang resinya sudah diterima & siapkan sertifikat donaturnya';

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');
        $batas = max(1, (int) $this->option('limit'));

        $idDiterima = DB::table('status_pengiriman')->where('slug', 'diterima')->value('id');

        if (! $idDiterima) {
            $this->error('Status "diterima" tidak ada di tabel status_pengiriman.');

            return self::FAILURE;
        }

        // Batch yang punya resi DAN seluruh resinya sudah diterima. Satu query
        // agregat, bukan satu query per batch (15 ribu batch). Status batch
        // sengaja TIDAK disaring — lihat catatan di atas.
        $idBatch = WakafBatch::query()
            ->whereHas('pengiriman')
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM pengiriman p
                WHERE p.wakaf_batch_id = wakaf_batches.id
                  AND p.status_id <> ?
            )', [$idDiterima])
            ->orderBy('id')
            ->limit($batas)
            ->pluck('id');

        $jumlahSertifikat = 0;
        $jumlahBatch = 0;

        foreach ($idBatch as $id) {
            DB::transaction(function () use ($id, $idDiterima, $kering, &$jumlahSertifikat, &$jumlahBatch) {
                $batch = WakafBatch::query()->lockForUpdate()->find($id);

                if (! $batch || $batch->status === 'cancelled') {
                    return;
                }

                // Penanda idempoten: batchnya sudah selesai bila tidak ada lagi
                // resi diterima yang belum bertanda sertifikat.
                $belumBertanda = Pengiriman::query()
                    ->where('wakaf_batch_id', $batch->id)
                    ->where('status_id', $idDiterima)
                    ->where('sertifikat_generated', false)
                    ->exists();

                if (! $belumBertanda) {
                    return;
                }

                if ($kering) {
                    $this->line(sprintf(
                        '  [kering] %s — %d resi diterima, belum bersertifikat',
                        $batch->batch_code,
                        Pengiriman::query()->where('wakaf_batch_id', $batch->id)->where('status_id', $idDiterima)->count()
                    ));

                    return;
                }

                // 1. Catatan sertifikat untuk tiap donatur yang resinya diterima.
                //    Pembuatan CATATAN tidak butuh template — hanya PDF-nya yang
                //    butuh, dan PDF dibuat saat diunduh (on-demand). Idempoten:
                //    donatur yang sudah punya sertifikat konsolidasi dilewati.
                //    Disaring per-resi, bukan hanya mengandalkan saringan batch di
                //    atas: batch dengan resi campuran (sebagian diterima, sebagian
                //    masih jalan) tetap harus hanya memproses yang sudah diterima.
                $idDonatur = Pengiriman::query()
                    ->where('wakaf_batch_id', $batch->id)
                    ->where('status_id', $idDiterima)
                    ->distinct()
                    ->pluck('donatur_id')
                    ->filter();

                $layanan = app(ConsolidatedCertificateService::class);

                foreach ($idDonatur as $idDonaturSatu) {
                    $donatur = Donatur::query()->find($idDonaturSatu);

                    if ($donatur) {
                        $layanan->createConsolidatedCertificateRecord($donatur);
                        $jumlahSertifikat++;
                    }
                }

                // 2. Tandai resinya sudah bersertifikat + catat waktu terima.
                //    Tanpa backfill ini kolom sertifikat_generated tetap 0 dan
                //    tampilan yang bergantung padanya selalu bilang "belum".
                Pengiriman::query()
                    ->where('wakaf_batch_id', $batch->id)
                    ->where('status_id', $idDiterima)
                    ->update([
                        'sertifikat_generated' => true,
                        'received_at' => DB::raw('COALESCE(received_at, updated_at)'),
                    ]);

                // 3. Tutup batch — pakai logika yang sudah ada, jangan ditiru.
                //    Biasanya sudah "completed" karena observer; updateStatus()
                //    memastikan batch yang belum tersentuh ikut ditutup.
                $batch->updateStatus();

                if ($batch->status === 'completed') {
                    $jumlahBatch++;
                }
            });
        }

        $this->info("Diperiksa {$idBatch->count()} batch | ditutup {$jumlahBatch} | catatan sertifikat {$jumlahSertifikat}");

        if ($jumlahSertifikat > 0) {
            Log::info('Penutupan pengiriman selesai', [
                'batch_ditutup' => $jumlahBatch,
                'catatan_sertifikat' => $jumlahSertifikat,
            ]);
        }

        return self::SUCCESS;
    }
}
