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
        // Fix wakaf_batches table foreign key reference
        if (Schema::hasTable('wakaf_batches') && Schema::hasColumn('wakaf_batches', 'wakif_id')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                // Drop the old foreign key constraint
                $table->dropForeign(['wakif_id']);
                
                // Rename the column
                $table->renameColumn('wakif_id', 'donatur_id');
            });
            
            // Add the new foreign key constraint in a separate schema call
            Schema::table('wakaf_batches', function (Blueprint $table) {
                $table->foreign('donatur_id')->references('id')->on('donatur')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes
        if (Schema::hasTable('wakaf_batches') && Schema::hasColumn('wakaf_batches', 'donatur_id')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                // Drop the new foreign key constraint
                $table->dropForeign(['donatur_id']);
                
                // Rename the column back
                $table->renameColumn('donatur_id', 'wakif_id');
            });
            
            // Add the old foreign key constraint in a separate schema call
            Schema::table('wakaf_batches', function (Blueprint $table) {
                $table->foreign('wakif_id')->references('id')->on('wakif')->onDelete('cascade');
            });
        }
    }
};