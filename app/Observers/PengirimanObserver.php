<?php

namespace App\Observers;

use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PengirimanObserver
{
    public function __construct(private readonly WhatsAppNotifier $notifier) {}

    /**
     * Handle the Pengiriman "updated" event.
     */
    public function updated(Pengiriman $pengiriman): void
    {
        // Check if status was changed and pengiriman belongs to a wakaf batch
        if ($pengiriman->isDirty('status_id') && $pengiriman->wakaf_batch_id) {
            // Auto-update the wakaf batch status
            $pengiriman->wakafBatch?->hitungUlangStatusSetelahCommit();
        }

        // Selesaikan permintaan mushaf begitu pengiriman sampai tujuan.
        if ($pengiriman->wasChanged('status_id')) {
            $this->completeRelatedMushafRequest($pengiriman);
            $this->kabarkanKeDonatur($pengiriman);
        }
    }

    /**
     * Handle the Pengiriman "created" event.
     */
    public function created(Pengiriman $pengiriman): void
    {
        // When new pengiriman is created for a batch, update batch status
        if ($pengiriman->wakaf_batch_id) {
            $pengiriman->wakafBatch?->hitungUlangStatusSetelahCommit();
        }
    }

    /**
     * Handle the Pengiriman "deleted" event.
     */
    public function deleted(Pengiriman $pengiriman): void
    {
        // When pengiriman is deleted from a batch, update batch status
        if ($pengiriman->wakaf_batch_id) {
            $pengiriman->wakafBatch?->hitungUlangStatusSetelahCommit();
        }
    }

    /**
     * Tandai permintaan mushaf terkait sebagai selesai saat pengiriman diterima.
     *
     * Tanpa ini, status 'completed' hanya bisa diubah manual oleh admin padahal
     * 'completed' adalah satu-satunya status yang muncul di Peta Penyebaran
     * Distribusi — sehingga peta tetap kosong walau mushaf sudah sampai.
     *
     * Hanya transisi 'processed' → 'completed' yang diotomatiskan: permintaan yang
     * belum diproses (pending/reviewed/approved) atau sudah ditolak tidak boleh
     * ikut diselesaikan hanya karena tertaut ke sebuah pengiriman.
     */
    protected function completeRelatedMushafRequest(Pengiriman $pengiriman): void
    {
        $slug = StatusPengiriman::query()->whereKey($pengiriman->status_id)->value('slug');

        if ($slug !== 'diterima') {
            return;
        }

        $idPermintaan = MushafRequest::query()
            ->where(fn ($q) => $q
                ->where('pengiriman_id', $pengiriman->id)
                ->orWhereHas('pengirimans', fn ($sub) => $sub->whereKey($pengiriman->id)))
            ->where('status', 'processed')
            ->value('id');

        if (! $idPermintaan) {
            return;
        }

        $noResi = $pengiriman->no_resi;

        // Diputuskan SETELAH commit, di transaksi baru yang mengunci permintaannya.
        // Dulu diputuskan di dalam transaksi update status resi: dua resi terakhir
        // yang diterima bersamaan masing-masing hanya melihat dirinya sendiri sudah
        // diterima (snapshot REPEATABLE READ), sehingga keduanya melewatkan penyelesaian
        // dan permintaan macet di 'processed' selamanya. Setelah commit + kunci, siapa
        // pun yang menghitung terakhir melihat semua resi. (Pola yang sama dengan
        // WakafBatch::hitungUlangStatusSetelahCommit.)
        DB::afterCommit(function () use ($idPermintaan, $noResi) {
            DB::transaction(function () use ($idPermintaan, $noResi) {
                $mushafRequest = MushafRequest::whereKey($idPermintaan)->lockForUpdate()->first();

                if (! $mushafRequest || $mushafRequest->status !== 'processed') {
                    return;
                }

                // Sejak permintaan dipecah per mushaf, satu permintaan punya BANYAK
                // kiriman. Selesai berarti SELURUH kirimannya sudah sampai.
                if (! $mushafRequest->semuaPengirimanDiterima()) {
                    Log::info('Sebagian kiriman permintaan mushaf sudah diterima', [
                        'mushaf_request' => $mushafRequest->no_request,
                        'pengiriman' => $noResi,
                    ]);

                    return;
                }

                $mushafRequest->update(['status' => 'completed']);

                Log::info('Permintaan mushaf otomatis diselesaikan', [
                    'mushaf_request' => $mushafRequest->no_request,
                    'pengiriman' => $noResi,
                ]);
            });
        });
    }

    /**
     * Kabari donatur bahwa kiriman wakafnya sudah sampai.
     *
     * Hanya saat MASUK ke 'diterima' (wasChanged), bukan setiap kali resi
     * disimpan — kalau tidak, menyunting catatan resi yang sudah diterima akan
     * mengirim pesan kedua. Kunci anti-ganda di WhatsAppNotifier menjaga hal yang
     * sama dari sisi lain; keduanya sengaja ada.
     *
     * Pemanggilannya aman: WhatsAppNotifier tidak pernah melempar exception,
     * jadi perubahan status resi oleh staf gudang tidak bisa gagal gara-gara
     * notifikasi.
     */
    protected function kabarkanKeDonatur(Pengiriman $pengiriman): void
    {
        $slug = StatusPengiriman::query()->whereKey($pengiriman->status_id)->value('slug');

        if ($slug !== 'diterima') {
            return;
        }

        $donatur = $pengiriman->donatur;

        if (! $donatur) {
            return;
        }

        $this->notifier->antri(
            'resi_diterima',
            'resi_diterima:pengiriman:'.$pengiriman->id,
            $donatur->no_hp,
            [
                'nama' => $donatur->nama_donatur ?: 'Bapak/Ibu',
                'resi' => $pengiriman->no_resi,
            ],
            [
                'donatur_id' => $donatur->id,
                'pengiriman_id' => $pengiriman->id,
                'wakaf_batch_id' => $pengiriman->wakaf_batch_id,
                'nama' => $donatur->nama_donatur,
            ]
        );
    }
}
