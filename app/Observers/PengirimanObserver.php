<?php

namespace App\Observers;

use App\Models\Pengiriman;

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
}