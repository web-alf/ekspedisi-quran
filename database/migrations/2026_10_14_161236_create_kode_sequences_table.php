<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghitung nomor urut bersama untuk kode yang dulu dibuat dengan pola
 * "ambil nomor terakhir / count() lalu +1" tanpa kunci (nomor sertifikat,
 * batch_code, kode transfer stok). Dua proses bersamaan mendapat nomor yang
 * sama dan salah satunya gagal kena unique index. Satu baris per kunci
 * (mis. "CERT-EQ-2026-"), dikunci dengan lockForUpdate saat mengambil nomor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kode_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_sequences');
    }
};
