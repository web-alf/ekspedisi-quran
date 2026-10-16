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
        // Check if the column exists before trying to drop it
        if (Schema::hasColumn('galleries', 'category')) {
            Schema::table('galleries', function (Blueprint $table) {
                // Drop the index first before dropping the column
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('category')->default('landing');
        });
    }
};
