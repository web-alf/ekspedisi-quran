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
        Schema::create('tracking_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('status_pengiriman')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->datetime('tanggal_update');
            $table->string('lokasi')->nullable();
            $table->text('keterangan')->nullable();
            $table->json('foto_dokumentasi')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            
            // Index
            $table->index('pengiriman_id');
            $table->index('status_id');
            $table->index('user_id');
            $table->index('tanggal_update');
            
            // Composite index
            $table->index(['pengiriman_id', 'status_id']);
            $table->index(['pengiriman_id', 'tanggal_update']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_history');
    }
};
