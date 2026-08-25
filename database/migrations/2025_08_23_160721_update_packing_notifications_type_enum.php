<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip for SQLite (testing environment)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // MySQL-specific syntax to modify enum column
        DB::statement("ALTER TABLE packing_notifications MODIFY COLUMN type ENUM(
            'reminder',
            'warning',
            'alert',
            'completion',
            'success',
            'collaboration',
            'milestone',
            'seal_request',
            'shared_box_sealed',
            'shared_box_sealed_supervisor',
            'completion_workflow',
            'supervisor_alert',
            'assignment'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip for SQLite (testing environment)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Revert to original enum values
        DB::statement("ALTER TABLE packing_notifications MODIFY COLUMN type ENUM(
            'reminder',
            'warning',
            'alert',
            'completion'
        ) NOT NULL");
    }
};
