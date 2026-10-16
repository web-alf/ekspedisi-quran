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

        // Add 'assigned' to packing_boxes status enum
        DB::statement("ALTER TABLE packing_boxes MODIFY COLUMN status ENUM(
            'empty',
            'filling',
            'full',
            'sealed',
            'assigned'
        ) NOT NULL DEFAULT 'empty'");
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
        DB::statement("ALTER TABLE packing_boxes MODIFY COLUMN status ENUM(
            'empty',
            'filling',
            'full',
            'sealed'
        ) NOT NULL DEFAULT 'empty'");
    }
};
