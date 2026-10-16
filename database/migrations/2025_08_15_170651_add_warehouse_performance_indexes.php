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
        // Check what indexes are actually missing and add only those
        
        // Add indexes for pengiriman table if needed (these are likely missing)
        Schema::table('pengiriman', function (Blueprint $table) {
            // Composite index (status_id, jenis_quran_id) for filtering available items
            if (!Schema::hasIndex('pengiriman', 'idx_pengiriman_status_jenis')) {
                $table->index(['status_id', 'jenis_quran_id'], 'idx_pengiriman_status_jenis');
            }
            
            // Index for QR code lookups
            if (!Schema::hasIndex('pengiriman', 'idx_pengiriman_qr_code_path')) {
                $table->index('qr_code_path', 'idx_pengiriman_qr_code_path');
            }
        });
        
        // Add indexes for packing_items table if needed
        Schema::table('packing_items', function (Blueprint $table) {
            // Composite index (pengiriman_id, packed_at) for tracking when items were packed
            if (!Schema::hasIndex('packing_items', 'idx_packing_items_pengiriman_packed_at')) {
                $table->index(['pengiriman_id', 'packed_at'], 'idx_packing_items_pengiriman_packed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            if (Schema::hasIndex('pengiriman', 'idx_pengiriman_status_jenis')) {
                $table->dropIndex('idx_pengiriman_status_jenis');
            }
            if (Schema::hasIndex('pengiriman', 'idx_pengiriman_qr_code_path')) {
                $table->dropIndex('idx_pengiriman_qr_code_path');
            }
        });
        
        Schema::table('packing_items', function (Blueprint $table) {
            if (Schema::hasIndex('packing_items', 'idx_packing_items_pengiriman_packed_at')) {
                $table->dropIndex('idx_packing_items_pengiriman_packed_at');
            }
        });
    }
};