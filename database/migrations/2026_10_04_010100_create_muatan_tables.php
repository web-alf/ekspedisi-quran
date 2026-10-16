<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Muatan = sekumpulan resi yang diantar SATU kurir dalam satu perjalanan.
 *
 * Sebelumnya tidak ada wadah untuk "perjalanan" sama sekali: kurir punya 26.111
 * resi dan tak ada cara membedakan mana yang sedang dibawanya hari ini. Muatan
 * memberi wadah itu, sekaligus tempat memantau status distribusinya.
 *
 * Muatan SENGAJA tidak menyimpan status sendiri — status perjalanannya dibaca
 * dari status resi di dalamnya. Kalau muatan punya status sendiri, ia bisa
 * berbeda dari kenyataan resi-resinya dan tidak ada lagi sumber kebenaran tunggal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muatan', function (Blueprint $table) {
            $table->id();

            // Nomor unik muatan, mis. MUK-2026-00001
            $table->string('kode_muatan')->unique();

            // Kurir yang mengantar (role courier)
            $table->foreignId('kurir_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Siapa yang membuat/menyiapkan muatan
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->date('tanggal_muatan');
            $table->string('nama_muatan')->nullable();
            $table->text('catatan')->nullable();

            // Ringkasan: jumlah resi & mushaf yang dimuat. Disimpan supaya daftar
            // tidak perlu menjumlahkan ulang tiap baris (26 ribu resi total).
            $table->unsignedInteger('total_resi')->default(0);
            $table->unsignedInteger('total_mushaf')->default(0);

            $table->timestamps();

            $table->index(['tanggal_muatan', 'kurir_id']);
        });

        Schema::create('muatan_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('muatan_id')->constrained('muatan')->cascadeOnDelete();

            // Satu resi hanya boleh berada di satu muatan.
            $table->foreignId('pengiriman_id')->unique()
                ->constrained('pengiriman')->cascadeOnDelete();

            // Urutan kunjungan, bila kurir mau menata rute
            $table->unsignedInteger('urutan')->nullable();

            // Kapan resi ini dipindai MASUK ke muatan
            $table->timestamp('dimuat_at')->nullable();
            $table->foreignId('dimuat_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['muatan_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muatan_items');
        Schema::dropIfExists('muatan');
    }
};
