<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan tautan peta dari berkas import.
 *
 * Sebelumnya kolom ini dibuang begitu saja, dan koordinat hanya bisa diturunkan
 * saat import berjalan. Akibatnya, kalau penguraian alamat gagal sekali
 * (jaringan mati, layanan peta tidak menjawab), baris itu TIDAK PERNAH bisa
 * dilengkapi lagi — tautannya sudah hilang. Menyimpannya membuat pengisian
 * ulang mungkin dilakukan kapan saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->string('link_gmaps', 500)->nullable()->after('alamat_detail')
                ->comment('Tautan peta dari berkas import; sumber koordinat');
        });
    }

    public function down(): void
    {
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropColumn('link_gmaps');
        });
    }
};
