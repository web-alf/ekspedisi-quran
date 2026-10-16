<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop ENUM constraint on users.role column and change to STRING.
     * This allows adding unlimited new roles without migration.
     *
     * CRITICAL FIX: ENUM constraint prevented adding new roles beyond
     * the hardcoded list (super_admin, cs, warehouse, courier, supervisor)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop ENUM constraint and change to string (nullable)
            // This allows any role value to be stored
            $table->string('role', 50)->nullable()->change();
        });

        // Log migration for audit trail
        \Log::info('Migration: Changed users.role from ENUM to STRING', [
            'migration' => '2025_12_09_103001_change_users_role_column_to_string',
            'reason' => 'Drop ENUM constraint to support unlimited roles',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: Rolling back will re-apply ENUM constraint.
     * Any roles not in the ENUM list will cause errors!
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rollback to ENUM with 5 allowed values
            // WARNING: This will fail if there are users with other role values
            $table->enum('role', ['super_admin', 'cs', 'warehouse', 'courier', 'supervisor'])
                  ->nullable()
                  ->change();
        });
    }
};
