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
        // Add wakaf_batch_id and sequence_in_batch to pengiriman table
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->foreignId('wakaf_batch_id')->nullable()->after('wakif_id')
                  ->constrained('wakaf_batches')->onDelete('set null');
            $table->integer('sequence_in_batch')->nullable()->after('wakaf_batch_id')
                  ->comment('Urutan Al-Quran dalam batch (1, 2, 3, dst)');
            
            // Index for performance
            $table->index(['wakaf_batch_id', 'sequence_in_batch'], 'pengiriman_batch_sequence_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropIndex('pengiriman_batch_sequence_index');
            $table->dropForeign(['wakaf_batch_id']);
            $table->dropColumn(['wakaf_batch_id', 'sequence_in_batch']);
        });
    }
};
