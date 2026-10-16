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
        Schema::create('daily_packing_task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_packing_task_id')->constrained('daily_packing_tasks')->onDelete('cascade');
            $table->foreignId('pengiriman_id')->constrained('pengiriman');
            $table->boolean('is_packed')->default(false);
            $table->timestamp('assigned_at');
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();
            
            // Unique constraint: satu pengiriman hanya bisa di-assign ke satu task
            $table->unique('pengiriman_id');
            
            // Index untuk query
            $table->index(['daily_packing_task_id', 'is_packed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_packing_task_items');
    }
};