<?php

namespace App\Console\Commands;

use App\Models\Pengiriman;
use App\Models\WakafBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menyambung ulang resi lama ke batch wakafnya.
 *
 * Kenapa perlu: dari lima jalur pembuatan resi, hanya DonaturController::storeBatch
 * yang mengisi wakaf_batch_id. Impor donatur massal, permintaan mushaf, dan
 * penambahan item wakaf membiarkannya null — sehingga di produksi 0 dari 26.111
 * resi punya wakaf_batch_id, padahal 15.552 batch semuanya punya donatur.
 *
 * Akibat putusnya: relasi `WakafBatch::pengiriman()` selalu kosong, sehingga
 * WakafBatch::updateStatus() — yang sudah dipanggil PengirimanObserver dan
 * RefreshWakafBatchStatuses — selalu menyimpulkan "belum ada pengiriman" dan
 * menulis ulang status batch menjadi pending_distribution. Itu sebabnya status
 * batch tidak pernah maju walau resinya sudah berjalan.
 *
 * Sumbernya sudah diperbaiki di Pengiriman::boot() (tautkanKeBatchDonatur), jadi
 * perintah ini hanya untuk merapikan data yang sudah terlanjur ada.
 */
class TautkanPengirimanKeBatchCommand extends Command
{
    protected $signature = 'wakaf:tautkan-pengiriman
                          {--chunk=1000 : Jumlah resi yang diproses sekali jalan}
                          {--kering : Hanya hitung, tidak mengubah apa pun}';

    protected $description = 'Isi wakaf_batch_id pada resi lama dari batch milik donaturnya';

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');
        $ukuran = max(1, (int) $this->option('chunk'));

        $dasar = Pengiriman::query()
            ->whereNull('wakaf_batch_id')
            ->whereNotNull('donatur_id');

        $total = (clone $dasar)->count();

        if ($total === 0) {
            $this->info('Semua resi sudah tertaut ke batch.');

            return self::SUCCESS;
        }

        $this->info("Resi tanpa batch: {$total}");

        if ($kering) {
            // Hitung berapa yang donaturnya punya TEPAT SATU batch — hanya itu
            // yang aman ditautkan. Donatur dengan lebih dari satu batch ambigu.
            $aman = (clone $dasar)->whereIn('donatur_id', function ($q) {
                $q->select('donatur_id')
                    ->from('wakaf_batches')
                    ->groupBy('donatur_id')
                    ->havingRaw('COUNT(*) = 1');
            })->count();

            $this->line("  aman ditautkan (donatur punya tepat 1 batch): {$aman}");
            $this->line('  dilewati (donatur tanpa batch / batch ganda): '.($total - $aman));
            $this->warn('Mode kering: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $tertaut = 0;

        // MySQL menolak `UPDATE ... JOIN ... LIMIT` ("Incorrect usage of UPDATE and
        // LIMIT"), jadi pemotongan dilakukan lewat rentang id, bukan LIMIT.
        $idMin = (clone $dasar)->min('id');
        $idMaks = (clone $dasar)->max('id');

        if ($idMin !== null) {
            $this->info("Memproses id {$idMin}–{$idMaks} dengan langkah {$ukuran}...");
            $bar = $this->output->createProgressBar((int) ceil(($idMaks - $idMin + 1) / $ukuran));

            for ($mulai = $idMin; $mulai <= $idMaks; $mulai += $ukuran) {
                $akhir = $mulai + $ukuran - 1;

                // Satu UPDATE ... JOIN: jauh lebih cepat daripada memanggil model
                // satu per satu (26 ribu baris). Hanya baris dengan tepat satu batch.
                $tertaut += DB::update('
                    UPDATE pengiriman p
                    JOIN (
                        SELECT donatur_id, MIN(id) AS id_batch
                        FROM wakaf_batches
                        GROUP BY donatur_id
                        HAVING COUNT(*) = 1
                    ) b ON b.donatur_id = p.donatur_id
                    SET p.wakaf_batch_id = b.id_batch
                    WHERE p.wakaf_batch_id IS NULL
                      AND p.donatur_id IS NOT NULL
                      AND p.id BETWEEN ? AND ?
                ', [$mulai, $akhir]);

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info("Tertautkan: {$tertaut}");

        // Segarkan status batch yang baru mendapat resi. Tanpa ini status batch
        // tetap seperti hasil perhitungan lama (pending_distribution).
        $idTerdampak = WakafBatch::query()
            ->whereHas('pengiriman')
            ->pluck('id');

        $this->info("Menyegarkan status {$idTerdampak->count()} batch...");

        $bar = $this->output->createProgressBar($idTerdampak->count());

        foreach ($idTerdampak as $id) {
            WakafBatch::query()->find($id)?->updateStatus();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        Log::info('Penyambungan resi ke batch selesai', [
            'tertautkan' => $tertaut,
            'batch_disiapkan' => $idTerdampak->count(),
        ]);

        $sebaran = WakafBatch::query()
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $this->table(['Status batch', 'Jumlah'], $sebaran->map(fn ($j, $s) => [$s, $j])->values()->all());

        return self::SUCCESS;
    }
}
