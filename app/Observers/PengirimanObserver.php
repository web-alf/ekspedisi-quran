<?php

namespace App\Observers;

use App\Models\MushafRequest;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use Illuminate\Support\Facades\Log;

class PengirimanObserver
{
    /**
     * Handle the Pengiriman "updated" event.
     */
    public function updated(Pengiriman $pengiriman): void
    {
        // Check if status was changed and pengiriman belongs to a wakaf batch
        if ($pengiriman->isDirty('status_id') && $pengiriman->wakaf_batch_id) {
            // Auto-update the wakaf batch status
            $pengiriman->wakafBatch->updateStatus();
        }

        // Selesaikan permintaan mushaf begitu pengiriman sampai tujuan.
        if ($pengiriman->wasChanged('status_id')) {
            $this->completeRelatedMushafRequest($pengiriman);
        }
    }

    /**
     * Handle the Pengiriman "created" event.
     */
    public function created(Pengiriman $pengiriman): void
    {
        // When new pengiriman is created for a batch, update batch status
        if ($pengiriman->wakaf_batch_id) {
            $pengiriman->wakafBatch->updateStatus();
        }
    }

    /**
     * Handle the Pengiriman "deleted" event.
     */
    public function deleted(Pengiriman $pengiriman): void
    {
        // When pengiriman is deleted from a batch, update batch status
        if ($pengiriman->wakaf_batch_id) {
            $pengiriman->wakafBatch->updateStatus();
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

        $mushafRequest = MushafRequest::query()
            ->where('pengiriman_id', $pengiriman->id)
            ->where('status', 'processed')
            ->first();

        if (! $mushafRequest) {
            return;
        }

        $mushafRequest->update(['status' => 'completed']);

        Log::info('Permintaan mushaf otomatis diselesaikan', [
            'mushaf_request' => $mushafRequest->no_request,
            'pengiriman' => $pengiriman->no_resi,
        ]);
    }
}
