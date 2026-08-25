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
        Schema::create('daily_packing_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('tanggal_tugas');
            $table->integer('total_target')->default(80);
            $table->integer('total_selesai')->default(0);
            $table->integer('sisa_kemarin')->default(0); // Carry over dari hari sebelumnya
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'expired'])->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('notes')->nullable(); // Catatan jika ada kendala
            $table->timestamps();
            
            // Unique constraint: satu user hanya punya satu tugas per hari
            $table->unique(['user_id', 'tanggal_tugas']);
            
            // Index untuk query performance
            $table->index(['tanggal_tugas', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_packing_tasks');
    }
};