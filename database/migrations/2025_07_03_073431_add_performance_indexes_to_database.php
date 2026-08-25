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
        // Add index for packing_boxes.jenis_quran_id (for Quran type filtering)
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->index('jenis_quran_id', 'idx_packing_boxes_jenis_quran');
        });

        // Add index for daily_packing_tasks.assigned_by (for supervisor queries)
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->index('assigned_by', 'idx_daily_packing_tasks_assigned_by');
        });

        // Add index for user_performance.bulan (for monthly performance queries)
        Schema::table('user_performance', function (Blueprint $table) {
            $table->index('bulan', 'idx_user_performance_bulan');
        });

        // Add composite index for wakaf_items (donatur + status queries)
        Schema::table('wakaf_items', function (Blueprint $table) {
            $table->index(['donatur_id', 'status'], 'idx_wakaf_items_donatur_status');
        });

        // Add index for pengiriman.created_at (for date range queries)
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->index('created_at', 'idx_pengiriman_created_at');
        });

        // Add index for daily_packing_tasks date queries
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->index(['tanggal_tugas', 'status'], 'idx_daily_packing_tasks_date_status');
        });

        // Add index for packing_items queries by packed_at
        Schema::table('packing_items', function (Blueprint $table) {
            $table->index('packed_at', 'idx_packing_items_packed_at');
        });

        // Add index for mushaf_requests location queries
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'idx_mushaf_requests_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_boxes', function (Blueprint $table) {
            $table->dropIndex('idx_packing_boxes_jenis_quran');
        });

        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_daily_packing_tasks_assigned_by');
            $table->dropIndex('idx_daily_packing_tasks_date_status');
        });

        Schema::table('user_performance', function (Blueprint $table) {
            $table->dropIndex('idx_user_performance_bulan');
        });

        Schema::table('wakaf_items', function (Blueprint $table) {
            $table->dropIndex('idx_wakaf_items_donatur_status');
        });

        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropIndex('idx_pengiriman_created_at');
        });

        Schema::table('packing_items', function (Blueprint $table) {
            $table->dropIndex('idx_packing_items_packed_at');
        });

        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropIndex('idx_mushaf_requests_location');
        });
    }
};