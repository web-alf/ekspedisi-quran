<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status baru "Tiba di Tujuan" di antara "Proses Pengiriman" dan "Diterima Penerima".
 *
 * Keputusan pemilik sistem (16/10/2026): kurir memindai untuk terakhir kali di lokasi
 * penerima, tetapi yang menyatakan "Diterima" tetap manager/distribusi — pengantar
 * tidak memeriksa pekerjaannya sendiri. Status ini adalah titik tengahnya: barang sudah
 * diserahkan kurir (lengkap dengan nama penerima, foto, GPS), menunggu konfirmasi.
 *
 * `diterima` digeser ke urutan 8 karena validator PengirimanTrackingController hanya
 * mengizinkan maju TEPAT satu urutan: pengiriman(6) → tiba(7) → diterima(8).
 *
 * Ditulis lewat query builder (bukan seeder) karena tabel master produksi sudah berisi
 * baris buatan tangan (`antrian`) — seeder penuh berisiko menimpa yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('status_pengiriman')->where('slug', 'diterima')->update(['urutan' => 8]);

            if (DB::table('status_pengiriman')->where('slug', 'tiba')->exists()) {
                DB::table('status_pengiriman')->where('slug', 'tiba')->update(['urutan' => 7, 'is_active' => true]);

                return;
            }

            DB::table('status_pengiriman')->insert([
                'nama' => 'Tiba di Tujuan',
                'slug' => 'tiba',
                'deskripsi' => 'Kurir sudah menyerahkan Quran di lokasi penerima; menunggu konfirmasi distribusi',
                'warna' => 'indigo',
                'icon' => '📍',
                'urutan' => 7,
                'is_active' => true,
                'is_final' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $tiba = DB::table('status_pengiriman')->where('slug', 'tiba')->value('id');

            // Resi yang sudah memakai status ini tidak boleh kehilangan statusnya diam-diam.
            if ($tiba && DB::table('pengiriman')->where('status_id', $tiba)->exists()) {
                throw new RuntimeException('Masih ada resi berstatus "Tiba di Tujuan"; pindahkan dulu sebelum rollback.');
            }

            DB::table('status_pengiriman')->where('slug', 'tiba')->delete();
            DB::table('status_pengiriman')->where('slug', 'diterima')->update(['urutan' => 7]);
        });
    }
};
