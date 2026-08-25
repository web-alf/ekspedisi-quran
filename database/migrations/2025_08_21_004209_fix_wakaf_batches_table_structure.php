<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip for SQLite (testing environment)
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, just ensure donatur_id exists
            if (! Schema::hasColumn('wakaf_batches', 'donatur_id')) {
                Schema::table('wakaf_batches', function (Blueprint $table) {
                    $table->unsignedBigInteger('donatur_id')->after('id')->nullable();
                    $table->foreign('donatur_id')->references('id')->on('donatur')->onDelete('cascade');
                    $table->index('donatur_id');
                });
            }

            return;
        }

        // MySQL/MariaDB only
        // Check if wakif_id column exists
        if (Schema::hasColumn('wakaf_batches', 'wakif_id')) {
            // Drop foreign key constraint if exists
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = 'wakaf_batches'
                AND COLUMN_NAME = 'wakif_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
                AND TABLE_SCHEMA = DATABASE()
            ");

            if (count($foreignKeys) > 0) {
                Schema::table('wakaf_batches', function (Blueprint $table) use ($foreignKeys) {
                    $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
                });
            }

            // Drop the column
            Schema::table('wakaf_batches', function (Blueprint $table) {
                $table->dropColumn('wakif_id');
            });
        }

        // Add donatur_id column if it doesn't exist
        if (! Schema::hasColumn('wakaf_batches', 'donatur_id')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                $table->unsignedBigInteger('donatur_id')->after('id');
                $table->foreign('donatur_id')->references('id')->on('donatur')->onDelete('cascade');
                $table->index('donatur_id');
            });
        }

        // Update status enum to include 'ready' - MySQL only
        DB::statement("ALTER TABLE wakaf_batches MODIFY COLUMN status ENUM('pending_distribution', 'in_progress', 'completed', 'ready') DEFAULT 'pending_distribution'");
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

        Schema::table('wakaf_batches', function (Blueprint $table) {
            // Revert changes if needed
            if (Schema::hasColumn('wakaf_batches', 'donatur_id')) {
                $table->dropForeign(['donatur_id']);
                $table->dropColumn('donatur_id');
            }

            // Re-add wakif_id column
            if (! Schema::hasColumn('wakaf_batches', 'wakif_id')) {
                $table->unsignedBigInteger('wakif_id')->after('id');
            }

            // Revert status enum - MySQL only
            DB::statement("ALTER TABLE wakaf_batches MODIFY COLUMN status ENUM('pending_distribution', 'in_progress', 'completed') DEFAULT 'pending_distribution'");
        });
    }
};
