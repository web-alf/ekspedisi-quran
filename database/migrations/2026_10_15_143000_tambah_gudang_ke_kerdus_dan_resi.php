<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gudang tersimpan di KERDUS dan di RESI.
 *
 * Sebelum ini gudang hanya menempel di `users` dan `daily_packing_tasks`, sehingga
 * pertanyaan "mushaf ini ada di gudang mana?" tidak bisa dijawab: resi dan kerdus sama
 * sekali tidak membawa lokasi, dan rantai resi → isi kerdus → tugas → gudang mati begitu
 * tugasnya tidak bertagih gudang (di produksi: 354 tugas, 0 bertagih).
 *
 * Dengan kolom ini lokasi bisa dibaca dalam SATU langkah, dan tetap benar walau tugas
 * hariannya dihapus/diubah kemudian:
 *   - `packing_boxes.gudang_id` — diisi saat kerdus dibuat (gudang tugas → gudang
 *     pekerja/penugasa), jadi kerdus yang sudah jadi tidak bergantung pada tugasnya lagi;
 *   - `pengiriman.gudang_id` — kenyataan fisik mushaf: diambil dari kerdus saat masuk
 *     kerdus, atau dari gudang pelaku saat resinya bergerak di tahap gudang. Setelah
 *     terisi, hanya dokumen perpindahan (transfer_mushaf) yang boleh mengubahnya.
 *
 * NULL tetap berarti "belum diketahui" — kolom baru tidak memaksa data lama, dan produksi
 * memang belum pernah mengisi kerdus sama sekali (0 kerdus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->foreignId('gudang_id')->nullable()->after('daily_packing_task_id')
                ->constrained('gudang', 'id')->nullOnDelete();
        });

        Schema::table('pengiriman', function (Blueprint $table) {
            $table->foreignId('gudang_id')->nullable()->after('jenis_quran_id')
                ->constrained('gudang', 'id')->nullOnDelete();

            // Filter & hitungan per gudang di Data Resi.
            $table->index(['gudang_id', 'status_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropIndex(['gudang_id', 'status_id']);
            $table->dropConstrainedForeignId('gudang_id');
        });

        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gudang_id');
        });
    }
};
