<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pindai kerdus sisi distribusi menjadi VERIFIKASI ISI, bukan sekadar catatan.
 *
 * Petugas menghitung isi fisik kerdus lalu mengetik jumlahnya; sistem membandingkan
 * dengan isi kerdus di data (`jumlah_mushaf`). Hasilnya:
 *   - 'cocok'   — jumlah fisik sama dengan data; kerdus boleh dimuat ke kurir.
 *   - 'selisih' — berbeda; kerdus DIBLOKIR dari muatan sampai dipindai ulang dan cocok.
 *   - null      — baris lama (sebelum verifikasi ada) atau kerdus tak terdaftar.
 *
 * Indeks (packing_box_id, id) melayani pertanyaan yang ditanyakan di setiap pemuatan:
 * "baris verifikasi TERAKHIR kerdus ini apa?"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pindai_kerdus_logs', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_fisik')->nullable()->after('jumlah_mushaf');
            $table->string('hasil', 10)->nullable()->after('jumlah_fisik');

            $table->index(['packing_box_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('pindai_kerdus_logs', function (Blueprint $table) {
            $table->dropIndex(['packing_box_id', 'id']);
            $table->dropColumn(['jumlah_fisik', 'hasil']);
        });
    }
};
