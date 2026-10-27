<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Proses Kedatangan/Penurunan" (slug `kedatangan`) ditutup rapat — 27/10/2026.
 *
 * Tahap ini sudah dihapus dari alur gudang sejak rombakan 13/10/2026 (mushaf datang
 * langsung masuk Antrian) dan barisnya sudah nonaktif di produksi, tetapi sisa pemakaian
 * di kode masih menampilkannya sebagai tahap dashboard & pilihan status. Sekarang kode
 * maupun datanya sama-sama tidak memakainya lagi.
 *
 * Barisnya TIDAK dihapus: 12 baris `status_histories` + 5 baris `tracking_history` lama
 * merujuk id-nya (FK `restrictOnDelete`) dan riwayat itu harus tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('status_pengiriman')
            ->where('slug', 'kedatangan')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Sengaja tidak diaktifkan kembali: menghapus tahap ini keputusan alur, bukan
        // perubahan skema. Rollback hanya membalik kalau pemilik memintanya.
    }
};
