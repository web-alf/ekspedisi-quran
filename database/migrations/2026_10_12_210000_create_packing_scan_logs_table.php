<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit scan yang GAGAL/dibatalkan di halaman packing.
 *
 * Scan sukses sudah punya jejak permanen (packing_items), tetapi scan yang
 * ditolak (QR tidak valid, resi tidak ada, sudah dipacking, tanpa tugas,
 * kerdus gagal dibuat, dst.) dulu hanya muncul sesaat di tab "Scan Terakhir"
 * milik browser lalu hilang saat refresh. Tabel ini menyimpannya permanen,
 * lengkap dengan SIAPA yang melakukan scan-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_packing_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('packing_box_id')->nullable()->constrained()->nullOnDelete();
            $table->string('qr_data', 500);
            $table->string('no_resi', 100)->nullable();
            $table->string('type', 50);
            $table->string('message', 1000);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_scan_logs');
    }
};
