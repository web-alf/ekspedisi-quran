<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fase 3 rombakan gudang (2026-10-13): stok mushaf tanpa muwakif.
 *
 * Keputusan pemilik sistem: mushaf bisa jadi STOK gudang dulu tanpa donatur
 * (diinput manual manager/SPV — "N dus jenis X di gudang Y"), pelan-pelan
 * dipotong saat muwakifnya dilampirkan, dan bisa dipindah antar gudang lewat
 * DOKUMEN TRANSFER yang dibuat lalu diterima SPV gudang tujuan.
 *
 * - stok_mushaf            : kardus-per-jenis-per-gudang yang masih di gudang.
 *   Dihitung dalam DUS (doz = 1 kerdus penuh jenis itu — BoxUnits), karena
 *   angka lapangan berbicara dalam dus, bukan eks.
 * - stok_mushaf_pergerakan : buku besar — tiap masuk/keluar/transfer tercatat
 *   siapa yang melakukan; tanpa ini "stok habis" tak bisa diaudit.
 * - transfer_stok          : dokumen pengalihan dari → ke, satu jenis,
 *   berstatus diproses/diterima/dibatalkan. Stok BARU berpindah saat SPV gudang
 *   tujuan menerima, bukan saat dokumen dibuat — barang fisik belum pasti tiba.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dokumen transfer dulu — stok_mushaf_pergerakan menunjuknya (FK).
        Schema::create('transfer_stok', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();            // TS-20261013-001
            $table->foreignId('gudang_asal_id')->constrained('gudang', 'id');
            $table->foreignId('gudang_tujuan_id')->constrained('gudang', 'id');
            $table->foreignId('jenis_quran_id')->constrained('jenis_quran', 'id');
            $table->unsignedInteger('jumlah_dus');
            $table->string('status', 20)->default('diproses'); // diproses, diterima, dibatalkan
            $table->string('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->foreignId('approved_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stok_mushaf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang', 'id')->cascadeOnDelete();
            $table->foreignId('jenis_quran_id')->constrained('jenis_quran', 'id')->cascadeOnDelete();
            $table->unsignedInteger('jumlah_dus')->default(0);
            $table->timestamps();

            $table->unique(['gudang_id', 'jenis_quran_id']);
        });

        Schema::create('stok_mushaf_pergerakan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang', 'id')->cascadeOnDelete();
            $table->foreignId('jenis_quran_id')->constrained('jenis_quran', 'id')->cascadeOnDelete();
            $table->string('tipe', 30);      // masuk, keluar, transfer_masuk, transfer_keluar
            $table->integer('jumlah_dus');   // positif
            $table->string('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->foreignId('transfer_id')->nullable()->constrained('transfer_stok', 'id')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_mushaf_pergerakan');
        Schema::dropIfExists('transfer_stok');
        Schema::dropIfExists('stok_mushaf');
    }
};
