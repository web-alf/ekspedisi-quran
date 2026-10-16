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
        Schema::create('user_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('bulan', 7); // Format: YYYY-MM
            $table->integer('total_target')->default(0);
            $table->integer('total_achieved')->default(0);
            $table->decimal('achievement_rate', 5, 2)->default(0); // Percentage
            $table->integer('total_hari_kerja')->default(0);
            $table->integer('total_hari_complete')->default(0);
            $table->integer('total_carry_over')->default(0); // Total mushaf carry over
            $table->integer('warning_count')->default(0);
            $table->decimal('avg_completion_time', 5, 2)->nullable(); // Rata-rata jam selesai
            $table->timestamps();
            
            // Unique constraint: satu user satu record per bulan
            $table->unique(['user_id', 'bulan']);
            
            // Index untuk query
            $table->index(['bulan', 'achievement_rate']);
            $table->index(['user_id', 'bulan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_performance');
    }
};