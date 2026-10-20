<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Temuan lapangan (19/10/2026): mushaf Al-Qur'an ukuran A5 dan A6 datang dalam DUA
 * varian — ADA terjemahan dan TANPA terjemahan. Selama ini keduanya tercampur di satu
 * baris stok, sehingga "A5 tinggal 4 eks" tak bisa dijawab benar: 4 eks yang mana?
 * Keputusan pemilik sistem: variannya WAJIB dipilih setiap mencatat pergerakan stok,
 * bukan sekadar pelengkap.
 *
 * Varian ikut ke tiga tabel supaya bisa diaudit:
 * - stok_mushaf            : saldo per gudang × jenis × varian (kunci unik diperluas);
 * - stok_mushaf_pergerakan : buku besar — tiap baris menyebut varian yang bergerak;
 * - transfer_stok          : dokumen transfer menyebut varian, karena stok berpindah
 *   antar gudang per varian (asal dan tujuan memakai kunci yang sama).
 *
 * Baris lama (dibuat sebelum kolom ini ada) diasumsikan TANPA terjemahan; kalau fisiknya
 * berbeda, koreksi lewat pergerakan keluar/masuk — bukan dengan menyunting baris bebas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_mushaf', function (Blueprint $table) {
            $table->boolean('terjemahan')->default(false)->after('jenis_quran_id');

            // URUTAN PENTING (MySQL/MariaDB): kunci unik lama (gudang_id, jenis_quran_id)
            // adalah satu-satunya indeks yang menopang foreign key `gudang_id`, jadi ia
            // TIDAK boleh dibuang lebih dulu — MySQL menolak dengan error 1553 ("needed in
            // a foreign key constraint"). Kunci unik baru dibuat dulu (awalannya juga
            // gudang_id, jadi bisa menggantikan perannya), baru yang lama dibuang.
            $table->unique(['gudang_id', 'jenis_quran_id', 'terjemahan'], 'stok_mushaf_gudang_jenis_varian_unique');
            $table->dropUnique('stok_mushaf_gudang_id_jenis_quran_id_unique');
        });

        Schema::table('stok_mushaf_pergerakan', function (Blueprint $table) {
            $table->boolean('terjemahan')->default(false)->after('jenis_quran_id');
        });

        Schema::table('transfer_stok', function (Blueprint $table) {
            $table->boolean('terjemahan')->default(false)->after('jenis_quran_id');
        });
    }

    public function down(): void
    {
        Schema::table('stok_mushaf', function (Blueprint $table) {
            // Kebalikan dari up(): kunci unik 2 kolom dipasang lagi SEBELUM yang 3 kolom
            // dibuang, supaya FK `gudang_id` tetap punya indeks penopang.
            $table->unique(['gudang_id', 'jenis_quran_id']);
            $table->dropUnique('stok_mushaf_gudang_jenis_varian_unique');
            $table->dropColumn('terjemahan');
        });

        Schema::table('stok_mushaf_pergerakan', fn (Blueprint $table) => $table->dropColumn('terjemahan'));
        Schema::table('transfer_stok', fn (Blueprint $table) => $table->dropColumn('terjemahan'));
    }
};
