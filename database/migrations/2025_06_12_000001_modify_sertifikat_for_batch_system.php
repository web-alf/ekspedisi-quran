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
        Schema::table('sertifikat', function (Blueprint $table) {
            // 1. Add wakaf_batch_id column
            $table->foreignId('wakaf_batch_id')->nullable()->after('id')->constrained('wakaf_batches')->onDelete('cascade');

            // 2. Make pengiriman_id nullable for transition
            $table->foreignId('pengiriman_id')->nullable()->change();
        });

        // Note: A separate script might be needed to populate wakaf_batch_id for existing certificates.
        // For now, we assume new certificates will use the batch system.

        Schema::table('sertifikat', function (Blueprint $table) {
            // 3. Drop the old foreign key and column after transition if desired
            // It's safer to do this in steps. For now, we keep it nullable.
            // $table->dropForeign(['pengiriman_id']);
            // $table->dropColumn('pengiriman_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sertifikat', function (Blueprint $table) {
            $table->dropForeign(['wakaf_batch_id']);
            $table->dropColumn('wakaf_batch_id');
            $table->foreignId('pengiriman_id')->nullable(false)->change();
        });
    }
};