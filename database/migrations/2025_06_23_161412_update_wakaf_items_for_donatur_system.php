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
        Schema::table('wakaf_items', function (Blueprint $table) {
            // Add donatur_id column
            $table->unsignedBigInteger('donatur_id')->nullable()->after('id');
            
            // Add foreign key constraint
            $table->foreign('donatur_id')->references('id')->on('donatur')->onDelete('cascade');
            
            // Keep donation_id for now but make it nullable
            $table->unsignedBigInteger('donation_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wakaf_items', function (Blueprint $table) {
            // Remove foreign key and column
            $table->dropForeign(['donatur_id']);
            $table->dropColumn('donatur_id');
            
            // Restore donation_id as not nullable
            $table->unsignedBigInteger('donation_id')->nullable(false)->change();
        });
    }
};