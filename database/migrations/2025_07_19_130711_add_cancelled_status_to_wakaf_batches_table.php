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
        // Check if we're using SQLite for testing
        if (config('database.default') === 'sqlite') {
            // SQLite doesn't support ENUM modification, so skip this migration in tests
            return;
        }

        // Add 'cancelled' status to the enum for MySQL
        DB::statement("ALTER TABLE wakaf_batches MODIFY COLUMN status ENUM('pending_distribution', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending_distribution'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if we're using SQLite for testing
        if (config('database.default') === 'sqlite') {
            // SQLite doesn't support ENUM modification, so skip this migration in tests
            return;
        }

        // Remove 'cancelled' status from the enum for MySQL
        DB::statement("ALTER TABLE wakaf_batches MODIFY COLUMN status ENUM('pending_distribution', 'in_progress', 'completed') DEFAULT 'pending_distribution'");
    }
};
