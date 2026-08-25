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
        Schema::create('packing_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_packing_task_id')->constrained('daily_packing_tasks')->onDelete('cascade');
            $table->string('kode_kerdus', 20)->unique(); // Format: KB-YYYYMMDD-XXX
            $table->integer('kapasitas')->default(20);
            $table->integer('jumlah_terisi')->default(0);
            $table->enum('status', ['empty', 'filling', 'full', 'sealed'])->default('empty');
            $table->timestamp('sealed_at')->nullable();
            $table->string('seal_code')->nullable(); // Kode segel untuk verifikasi
            $table->timestamps();
            
            // Index untuk query
            $table->index(['daily_packing_task_id', 'status']);
            $table->index('kode_kerdus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_boxes');
    }
};