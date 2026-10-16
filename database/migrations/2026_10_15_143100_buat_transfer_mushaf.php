<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen perpindahan mushaf wakaf (ber-resi) antar gudang.
 *
 * Stok mushaf TANPA muwakif sudah punya `transfer_stok`; mushaf wakaf belum punya apa pun,
 * padahal justru mushaf ber-resi yang punya tujuan kirim dan identitas wakif — kalau ia
 * dipindah gudang tanpa catatan, `pengiriman.gudang_id` menjadi bohong dan tidak ada yang
 * tahu kapan ia pindah.
 *
 * Aturan yang membentuk dokumen ini (mengikuti pola transfer_stok):
 *   - Dibuat oleh gudang ASAL, lalu DITERIMA gudang TUJUAN; lokasi mushaf (`pengiriman
 *     .gudang_id`) baru berubah saat diterima — bukan saat dokumen dibuat. Kalau berubah
 *     saat dibuat, mushaf yang masih di jalan sudah dihitung di gudang tujuan.
 *   - Gudang tujuan TIDAK boleh menerima dokumen yang ia buat sendiri.
 *   - Gudang asal boleh membatalkan selama statusnya masih `diproses`.
 *   - Satu resi hanya boleh ada di satu dokumen yang belum selesai (dijaga di kode, dan
 *     kolom `pengiriman_id` dibuat UNIK untuk menutup jalur balapan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_mushaf', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();            // TM-20261015-001
            $table->foreignId('gudang_asal_id')->constrained('gudang', 'id');
            $table->foreignId('gudang_tujuan_id')->constrained('gudang', 'id');
            $table->string('status', 20)->default('diproses'); // diproses, diterima, dibatalkan
            $table->unsignedInteger('jumlah_resi')->default(0);
            $table->unsignedInteger('jumlah_eks')->default(0);
            $table->string('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->foreignId('received_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'gudang_tujuan_id']);
        });

        Schema::create('transfer_mushaf_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_mushaf_id')->constrained('transfer_mushaf', 'id')->cascadeOnDelete();
            $table->foreignId('pengiriman_id')->constrained('pengiriman', 'id')->cascadeOnDelete();
            $table->timestamps();

            // Satu resi cukup sekali: mencegah resi yang sama dimasukkan ke dua dokumen
            // (dan mencegah dua dokumen paralel memperebutkan mushaf yang sama).
            $table->unique('pengiriman_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_mushaf_items');
        Schema::dropIfExists('transfer_mushaf');
    }
};
