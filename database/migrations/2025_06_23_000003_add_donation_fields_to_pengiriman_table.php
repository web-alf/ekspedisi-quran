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
        Schema::table('pengiriman', function (Blueprint $table) {
            // Add foreign keys to link with new donation system
            $table->unsignedBigInteger('donation_id')->nullable()->after('wakif_id');
            $table->unsignedBigInteger('wakaf_item_id')->nullable()->after('donation_id');
            
            // Add indexes
            $table->index('donation_id');
            $table->index('wakaf_item_id');
            
            // Add foreign keys
            $table->foreign('donation_id')->references('id')->on('donations')->onDelete('set null');
            $table->foreign('wakaf_item_id')->references('id')->on('wakaf_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['donation_id']);
            $table->dropForeign(['wakaf_item_id']);
            
            // Drop indexes
            $table->dropIndex(['donation_id']);
            $table->dropIndex(['wakaf_item_id']);
            
            // Drop columns
            $table->dropColumn(['donation_id', 'wakaf_item_id']);
        });
    }
};