<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak serah terima oleh kurir — satu baris per resi (1 resi = 1 mushaf).
 *
 * Dua jenis:
 *   - `tujuan`       : diserahkan di lokasi penerima yang tertulis di resi.
 *   - `tengah_jalan` : diminta orang lain dalam perjalanan; resi DIALIHKAN ke penerima
 *                      baru. Data penerima lama disalin ke sini karena di tabel resi
 *                      nilainya ditimpa — tanpa salinan ini, asal tujuan mushaf hilang.
 *
 * `mushaf_request_id_asal` disimpan karena resi yang dialihkan DILEPAS dari permintaan
 * lembaganya (supaya permintaan bisa selesai dengan jumlah yang benar-benar sampai);
 * kolom ini yang membuat catatan "2 diserahkan di jalan" tetap bisa dihitung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_kurir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->cascadeOnDelete();
            $table->foreignId('muatan_id')->nullable()->constrained('muatan')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis', 20);
            // Diisi bila dipindai lewat QR kerdus (seluruh isi kerdus diserahkan sekaligus).
            $table->string('kode_kerdus', 60)->nullable();

            $table->string('nama_penerima');
            $table->string('no_hp_penerima', 30)->nullable();
            $table->text('alamat_penerima')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('foto')->nullable();
            $table->text('catatan')->nullable();

            // Tujuan awal sebelum dialihkan (hanya untuk `tengah_jalan`).
            $table->foreignId('mushaf_request_id_asal')->nullable()->constrained('mushaf_requests')->nullOnDelete();
            $table->string('nama_lembaga_asal')->nullable();
            $table->string('nama_penerima_asal')->nullable();
            $table->text('alamat_asal')->nullable();

            $table->timestamps();

            $table->index(['jenis', 'created_at']);
            $table->index(['muatan_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_kurir');
    }
};
