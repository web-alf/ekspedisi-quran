<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan notifikasi WhatsApp (StarSender).
 *
 * Ini membangun ulang apa yang pernah ada lalu dihapus permanen oleh migrasi
 * 2025_08_15_190504_remove_whatsapp_functionality — bukan memulihkannya, sebab
 * `down()` migrasi itu sengaja melempar exception dan tabelnya sudah tidak ada
 * di produksi. Rancangannya sengaja dibuat berbeda: tidak ada tabel kontak
 * (sumber nomor adalah data donatur/pengurus yang sudah ada) dan tidak ada
 * tabel pengaturan terpisah per jenis pesan (itu tugas tabel template).
 *
 * Satu baris saja: `active()` mengambil baris pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('starsender');

            // Kunci akses device StarSender. Disimpan TERENKRIPSI lewat cast
            // 'encrypted' di model, jadi harus TEXT — hasil enkripsi jauh lebih
            // panjang dari 255 karakter dan akan terpotong di kolom string.
            $table->text('api_key')->nullable();

            $table->string('base_url')->default('https://api.starsender.online');

            // Nomor pengirim hanya untuk ditampilkan di halaman pengaturan
            // (nomor sebenarnya adalah nomor device di dashboard StarSender).
            $table->string('sender_number')->nullable();

            $table->boolean('is_active')->default(false);

            // Jeda antar pesan (detik) dan batas laju per menit, keduanya untuk
            // menekan risiko nomor diblokir WhatsApp. Dikirim ke StarSender
            // sebagai `delay`, dan ditegakkan sendiri oleh WhatsAppRateLimiter.
            $table->unsignedSmallInteger('delay_seconds')->default(3);
            $table->unsignedSmallInteger('max_per_minute')->default(20);

            // Jam tenang: bila diisi, pesan tidak dikirim di rentang ini
            // (mis. agar hasil penutupan batch pukul 02:30 menunggu pagi).
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
