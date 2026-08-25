<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengiriman', function (Blueprint $table) {
            $table->id();
            $table->string('no_resi', 50)->unique();
            $table->foreignId('wakif_id')->constrained('wakif')->restrictOnDelete();
            $table->foreignId('jenis_quran_id')->constrained('jenis_quran')->restrictOnDelete();
            $table->integer('jumlah_quran')->default(1);
            $table->date('tanggal_wakaf');
            $table->foreignId('status_id')->default(1)->constrained('status_pengiriman')->restrictOnDelete();
            $table->string('qr_code_path')->nullable();
            $table->text('qr_code_data')->nullable();
            $table->text('alamat_tujuan')->nullable();
            $table->string('nama_penerima')->nullable();
            $table->string('no_hp_penerima', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('sertifikat_generated')->default(false);
            $table->string('sertifikat_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            
            // Index
            $table->index('no_resi');
            $table->index('wakif_id');
            $table->index('jenis_quran_id');
            $table->index('status_id');
            $table->index('created_by');
            $table->index('tanggal_wakaf');
            $table->index('created_at');
            
            // Composite index untuk query kompleks
            $table->index(['status_id', 'created_at']);
            $table->index(['wakif_id', 'status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengiriman');
    }
};
