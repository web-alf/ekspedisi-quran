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
        // Add missing columns to packing_boxes table
        Schema::table('packing_boxes', function (Blueprint $table) {
            // Add columns for completion workflow
            if (! Schema::hasColumn('packing_boxes', 'completion_triggered_at')) {
                $table->timestamp('completion_triggered_at')->nullable()->after('sealed_at');
            }

            if (! Schema::hasColumn('packing_boxes', 'seal_requested_at')) {
                $table->timestamp('seal_requested_at')->nullable()->after('completion_triggered_at');
            }

            if (! Schema::hasColumn('packing_boxes', 'seal_requested_by')) {
                $table->foreignId('seal_requested_by')->nullable()->after('seal_requested_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_boxes', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['seal_requested_by']);

            // Drop columns
            $table->dropColumn([
                'completion_triggered_at',
                'seal_requested_at',
                'seal_requested_by',
            ]);
        });
    }
};
