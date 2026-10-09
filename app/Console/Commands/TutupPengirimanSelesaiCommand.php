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
 * Kenapa perintah terjadwal, bukan hook saat status berubah: notifikasi ke
 * donatur TIDAK BISA dikerjakan — tidak ada layanan WhatsApp di aplikasi ini,
 * dan job yang dirujuk kode lama (`SendDeliveryNotificationToWakif`,
 * `SendCertificateToWakif`) memang tidak pernah ada. Menyambung ke sesuatu yang
 * tidak ada akan membuat setiap perubahan status meledak. Yang bisa dikerjakan
 * sekarang: penutupan batch + catatan sertifikat. Aman dijalankan berulang.
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

        // Batch yang berjalan, punya resi, dan SELURUH resinya sudah diterima.
        // Satu query agregat — bukan satu query per batch (15 ribu batch).
        $idBatch = WakafBatch::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereHas('pengiriman')
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM pengiriman p
                WHERE p.wakaf_batch_id = wakaf_batches.id
                  AND p.status_id <> ?
            )', [$idDiterima])
            ->orderBy('id')
            ->limit($batas)
            ->pluck('id');

        if ($idBatch->isEmpty()) {
            $this->info('Tidak ada batch yang seluruh resinya sudah diterima.');

            return self::SUCCESS;
        }

        $this->info("Batch siap ditutup: {$idBatch->count()}");

        if ($kering) {
            $this->table(
                ['Batch', 'Resi diterima', 'Donatur'],
                WakafBatch::query()
                    ->whereIn('id', $idBatch)
                    ->withCount('pengiriman')
                    ->limit(20)
                    ->get()
                    ->map(fn ($b) => [
                        $b->batch_code,
                        $b->pengiriman_count,
                        Donatur::query()->whereKey($b->donatur_id)->value('nama_donatur') ?? '—',
                    ])->all()
            );
            $this->warn('Mode kering: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $jumlahSertifikat = 0;
        $jumlahBatch = 0;

        foreach ($idBatch as $id) {
            DB::transaction(function () use ($id, $idDiterima, &$jumlahSertifikat, &$jumlahBatch) {
                $batch = WakafBatch::query()->lockForUpdate()->find($id);

                if (! $batch || in_array($batch->status, ['completed', 'cancelled'], true)) {
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
                $batch->updateStatus();

                if ($batch->status === 'completed') {
                    $jumlahBatch++;
                }
            });
        }

        $this->info("Batch ditutup: {$jumlahBatch} | catatan sertifikat: {$jumlahSertifikat}");

        Log::info('Penutupan pengiriman selesai', [
            'batch_ditutup' => $jumlahBatch,
            'catatan_sertifikat' => $jumlahSertifikat,
        ]);

        return self::SUCCESS;
    }
}
