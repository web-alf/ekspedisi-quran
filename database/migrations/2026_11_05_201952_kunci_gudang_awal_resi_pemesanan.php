<?php

use App\Support\GudangAwal;
use Illuminate\Database\Migrations\Migration;

/**
 * Beri gudang awal ke resi PEMESANAN lama yang belum bergudang.
 *
 * Aturan pemilik sistem (05/11/2026): bergantian per 1.000 resi dari yang terlama —
 * 1–1.000 Semarang, 1.001–2.000 Boyolali, dst. Resi baru melanjutkan urutan yang sama
 * lewat hook `creating` Pengiriman. Resi yang sudah bergudang, atau sudah lewat tahap
 * pemesanan, tidak disentuh.
 *
 * Dijalankan sebagai migrasi, bukan perintah artisan: deploy hanya menjalankan migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        GudangAwal::alokasikanResiLama();
    }

    /**
     * Tidak dibalik: setelah migrasi, resi yang dialokasikan tidak bisa dibedakan dari resi
     * yang gudangnya diisi saat dikemas atau lewat dokumen perpindahan.
     */
    public function down(): void
    {
        //
    }
};
