<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah lembaga yang dilayani satu muatan.
 *
 * Diisi MANUAL oleh penyusun muatan, bukan dihitung dari resi di dalamnya.
 * Alasannya urutan kerja: muatan dibuat LEBIH DULU, resinya baru dipindai
 * sesudahnya. Saat form dibuka, muatan masih kosong — tidak ada apa pun untuk
 * dihitung, padahal jumlah lembaga justru perlu dicatat sejak awal sebagai
 * rencana kunjungan yang dibawa kurir.
 *
 * Nullable: dikosongkan berarti "belum diisi", bukan nol. Angka nol akan
 * menyesatkan pembaca menjadi "tidak melayani lembaga mana pun".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('muatan', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_lembaga')->nullable()->after('catatan')
                ->comment('Jumlah lembaga yang dilayani muatan ini; diisi manual');
        });
    }

    public function down(): void
    {
        Schema::table('muatan', function (Blueprint $table) {
            $table->dropColumn('jumlah_lembaga');
        });
    }
};
