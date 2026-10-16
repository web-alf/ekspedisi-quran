<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fase 2 rombakan gudang (2026-10-13): gudang jadi entitas data.
 *
 * Keputusan pemilik sistem: ada dua tempat fisik — Gudang Semarang dan Gudang
 * Boyolali. Manager Repacking (PJ) melihat informasi lengkap kedua gudang; di
 * bawahnya ada SPV per gudang yang bisa assign kerjaan packing ke freelancer
 * warehouse. Tabel ini pondasi untuk penugasan (tugas harian bertagih gudang)
 * dan Fase 3: stok mushaf tanpa muwakif per gudang + dokumen transfer antar
 * gudang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gudang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();          // SMG, BTL
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('gudang')->insert([
            ['kode' => 'SMG', 'nama' => 'Gudang Semarang', 'created_at' => now(), 'updated_at' => now()],
            ['kode' => 'BTL', 'nama' => 'Gudang Boyolali', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang');
    }
};
