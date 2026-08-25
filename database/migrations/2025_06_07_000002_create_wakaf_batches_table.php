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
        // Create wakaf_batches table
        Schema::create('wakaf_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wakif_id')->constrained('wakif')->onDelete('cascade');
            $table->string('batch_code', 50)->unique(); // WB-2025-00001
            $table->foreignId('jenis_quran_id')->constrained('jenis_quran')->restrictOnDelete();
            $table->integer('total_quran')->default(1);
            $table->date('tanggal_wakaf');
            $table->enum('status', ['pending_distribution', 'in_progress', 'completed'])->default('pending_distribution');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['wakif_id', 'tanggal_wakaf']);
            $table->index('batch_code');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wakaf_batches');
    }
};
