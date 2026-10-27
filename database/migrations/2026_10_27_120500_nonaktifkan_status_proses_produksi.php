<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Proses Produksi" (slug `produksi`) dinonaktifkan — keputusan pemilik sistem 27/10/2026.
 *
 * Proses cetak mushaf berjalan di luar gudang, dan tahap ini tidak pernah dipakai alur
 * nyata: di produksi 0 resi berstatus `produksi`, sementara 26.080 resi sudah langsung
 * `pemesanan` → `packing`. Status ini juga sudah dikeluarkan dari ranah gudang sejak
 * rombakan 13/10/2026, jadi yang tersisa hanya pilihan status yang membingungkan.
 *
 * Barisnya TIDAK dihapus: 52 baris `status_histories` dan 25 baris `tracking_history`
 * lama masih merujuk id-nya (FK `restrictOnDelete`), dan riwayat itu harus tetap terbaca
 * di halaman lacak resi lama. Menonaktifkan sudah cukup — `StatusPengiriman::active()`
 * menyaringnya dari setiap daftar pilihan, filter, dan tahap dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('status_pengiriman')
            ->where('slug', 'produksi')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('status_pengiriman')
            ->where('slug', 'produksi')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
