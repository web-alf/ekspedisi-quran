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
        Schema::create('mushaf_requests', function (Blueprint $table) {
            $table->id();
            $table->string('no_request')->unique()->nullable();
            
            // Data Lembaga
            $table->string('nama_lembaga');
            $table->text('alamat_lengkap');
            
            // Pengurus 1
            $table->string('nama_pengurus_1');
            $table->string('jabatan_pengurus_1');
            $table->string('whatsapp_pengurus_1');
            
            // Pengurus 2
            $table->string('nama_pengurus_2');
            $table->string('jabatan_pengurus_2');
            $table->string('whatsapp_pengurus_2');
            
            // Detail Permintaan
            $table->text('urgensi_request'); // Ceritakan urgensi
            $table->integer('jumlah_mushaf')->default(0);
            $table->integer('jumlah_iqra')->default(0);
            
            // Jenis Mushaf yang diminta (JSON untuk multiple selection)
            $table->json('jenis_mushaf_diminta'); // ['A5', 'A6', 'IQRA']
            
            // Sumber Informasi
            $table->string('sumber_info');
            
            // File uploads
            $table->string('foto_santri_path')->nullable();
            $table->string('foto_lembaga_path')->nullable();
            $table->string('file_nama_santri_path')->nullable();
            
            // Status dan approval
            $table->enum('status', ['pending', 'reviewed', 'approved', 'rejected', 'processed', 'completed'])->default('pending');
            $table->text('catatan_admin')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            // Relasi ke pengiriman (jika sudah diproses)
            $table->unsignedBigInteger('pengiriman_id')->nullable();
            $table->foreign('pengiriman_id')->references('id')->on('pengiriman')->onDelete('set null');
            
            // Admin yang handle
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index('no_request');
            $table->index('nama_lembaga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mushaf_requests');
    }
};
