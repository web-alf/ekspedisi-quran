<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean sekaligus riwayat kirim notifikasi WhatsApp.
 *
 * Satu baris = satu pesan yang HARUS terkirim ke satu nomor. Barisnya dibuat
 * SEBELUM dikirim (status 'menunggu'), jadi kegagalan selalu meninggalkan jejak
 * yang bisa dilihat dan dikirim ulang — bukan hilang tanpa bekas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();

            $table->string('event_key');

            // Kunci anti-ganda. Peristiwa yang sama untuk penerima yang sama
            // hanya boleh menghasilkan satu pesan, walau alurnya dijalankan dua
            // kali (mis. perintah penutup batch dijalankan ulang). Unik di level
            // DB supaya tetap aman saat dua proses berjalan bersamaan.
            $table->string('dedupe_key')->unique();

            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->foreignId('donatur_id')->nullable()->constrained('donatur')->nullOnDelete();
            $table->foreignId('pengiriman_id')->nullable()->constrained('pengiriman')->nullOnDelete();
            $table->foreignId('wakaf_batch_id')->nullable()->constrained('wakaf_batches')->nullOnDelete();

            $table->string('recipient');
            $table->string('recipient_name')->nullable();
            $table->text('body');

            $table->enum('status', ['menunggu', 'terkirim', 'gagal', 'dilewati'])->default('menunggu');
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Halaman antrean & riwayat selalu menyaring per status dan
            // mengurutkan terbaru dulu.
            $table->index(['status', 'created_at']);
            $table->index('event_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
