<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kembalikan 'cancelled' ke enum status wakaf_batches.
 *
 * Kenapa: migrasi 2025_08_21_004209_fix_wakaf_batches_table_structure menimpa
 * enum ini untuk menambah 'ready' — tapi daftarnya ditulis ulang tanpa
 * 'cancelled', sehingga nilai itu ikut hilang (sebelumnya ditambahkan oleh
 * migrasi 2025_07_19_130711).
 *
 * Akibatnya nyata: WakafBatch::updateStatus() menyetel status ke 'cancelled'
 * begitu SELURUH resi sebuah batch berstatus 'batal'. Di MySQL 8 (produksi)
 * penulisan nilai di luar enum gagal dengan "Data truncated for column 'status'",
 * dan karena updateStatus() dipanggil PengirimanObserver pada setiap perubahan
 * status, pembatalan resi terakhir sebuah batch akan melempar exception dan
 * menggagalkan transaksinya.
 *
 * Nilai lama tetap dipertahankan ('ready' tidak dibuang) — migrasi ini hanya
 * menambah, tidak mengganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE wakaf_batches MODIFY COLUMN status '.
            "ENUM('pending_distribution', 'in_progress', 'completed', 'ready', 'cancelled') ".
            "DEFAULT 'pending_distribution'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Hanya boleh mundur bila tidak ada baris yang memakai 'cancelled' —
        // kalau tidak, datanya akan terpotong menjadi string kosong.
        if (Schema::hasTable('wakaf_batches') && DB::table('wakaf_batches')->where('status', 'cancelled')->exists()) {
            throw new Exception('Tidak bisa dibatalkan: masih ada batch berstatus cancelled.');
        }

        DB::statement(
            'ALTER TABLE wakaf_batches MODIFY COLUMN status '.
            "ENUM('pending_distribution', 'in_progress', 'completed', 'ready') ".
            "DEFAULT 'pending_distribution'"
        );
    }
};
