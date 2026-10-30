<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Target periode per gudang (30/10/2026).
 *
 * Manager Gudang memberi target MINGGUAN (Senin–Minggu) atau BULANAN (kalender) per gudang,
 * opsional terbatas satu jenis/varian mushaf. Progresnya tidak disimpan: dijumlah dari
 * total_selesai tugas harian freelancer gudang itu di dalam rentang periode.
 *
 * `kunci_jenis` = "semua" atau nilai pilihan tugas ("1:1", "5:-") — kolom tidak-null supaya
 * unik per gudang+periode+jenis bisa dijaga database (unik dengan NULL tidak berlaku di MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_periode_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang', 'id')->cascadeOnDelete();
            $table->string('tipe', 10); // mingguan, bulanan
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->foreignId('jenis_quran_id')->nullable()->constrained('jenis_quran', 'id')->nullOnDelete();
            $table->boolean('terjemahan')->nullable();
            $table->string('kunci_jenis', 20)->default('semua');
            $table->unsignedInteger('target');
            $table->string('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gudang_id', 'tipe', 'tanggal_mulai', 'kunci_jenis'], 'target_periode_unik');
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_periode_gudang');
    }
};
