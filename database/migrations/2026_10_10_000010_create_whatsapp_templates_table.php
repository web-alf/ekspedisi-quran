<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template pesan WhatsApp.
 *
 * Jenis notifikasi apa saja yang benar-benar dikirim adalah DATA, bukan kode:
 * satu baris = satu peristiwa yang bisa diaktifkan/dimatikan tanpa deploy.
 * Itu disengaja supaya pertanyaan "peristiwa mana yang dikirim" bisa dijawab
 * dengan menyalakan/mematikan baris, tanpa mengubah program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();

            // Kunci peristiwa yang stabil — dipakai kode untuk merujuk template,
            // jadi JANGAN diganti setelah dipakai (mengganti nama akan membuat
            // notifikasi diam-diam berhenti tanpa pesan galat). Judul yang
            // dilihat pengguna ada di kolom `title`.
            $table->string('name')->unique();

            $table->string('category');

            // Judul untuk manusia, boleh diganti kapan saja.
            $table->string('title');

            // Isi pesan dengan placeholder {nama}, {batch}, {resi}, {link}.
            $table->text('content');

            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
