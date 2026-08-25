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
        // Add indexes for better performance with jenis-based box segregation
        Schema::table('packing_boxes', function (Blueprint $table) {
            // Index for finding boxes by jenis and status
            $table->index(['jenis_quran_id', 'status'], 'idx_packing_boxes_jenis_status');
            
            // Index for finding boxes by task and jenis
            $table->index(['daily_packing_task_id', 'jenis_quran_id'], 'idx_packing_boxes_task_jenis');
            
            // Index for finding boxes by task and status
            $table->index(['daily_packing_task_id', 'status'], 'idx_packing_boxes_task_status');
        });
        
        // Add indexes to daily_packing_task_items for jenis analysis
        Schema::table('daily_packing_task_items', function (Blueprint $table) {
            // Index for task items lookup
            $table->index(['daily_packing_task_id', 'is_packed'], 'idx_task_items_task_packed');
        });
        
        // Add indexes to pengiriman for jenis-based queries
        Schema::table('pengiriman', function (Blueprint $table) {
            // Index for jenis and status lookup
            $table->index(['jenis_quran_id', 'status_id'], 'idx_pengiriman_jenis_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->dropIndex('idx_packing_boxes_jenis_status');
            $table->dropIndex('idx_packing_boxes_task_jenis');
            $table->dropIndex('idx_packing_boxes_task_status');
        });
        
        Schema::table('daily_packing_task_items', function (Blueprint $table) {
            $table->dropIndex('idx_task_items_task_packed');
        });
        
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropIndex('idx_pengiriman_jenis_status');
        });
    }
};
