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
        Schema::table('users', function (Blueprint $table) {
            // Make role field nullable since we're using Spatie roles now
            $table->enum('role', ['super_admin', 'cs', 'warehouse', 'courier', 'supervisor'])
                  ->nullable()
                  ->default(null)
                  ->change();
        });
        
        // Clear legacy role field for all users to use Spatie roles
        \DB::table('users')->update(['role' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'cs', 'warehouse', 'courier'])
                  ->nullable(false)
                  ->default('super_admin')
                  ->change();
        });
    }
};