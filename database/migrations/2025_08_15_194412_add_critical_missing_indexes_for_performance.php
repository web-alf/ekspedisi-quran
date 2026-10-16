<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds critical indexes identified during performance analysis.
     * These indexes will significantly improve query performance for dashboard,
     * filtering, and analytics operations.
     */
    public function up(): void
    {
        // PENGIRIMAN TABLE INDEXES
        // These indexes support dashboard queries, donatur filtering, and delivery analytics

        // Composite index for dashboard queries: donatur filtering with status and date sorting
        // Supports queries like: WHERE donatur_id = ? AND status_id = ? ORDER BY created_at DESC
        if (! $this->indexExists('pengiriman', 'idx_pengiriman_donatur_status_date')) {
            Schema::table('pengiriman', function (Blueprint $table) {
                $table->index(['donatur_id', 'status_id', 'created_at'], 'idx_pengiriman_donatur_status_date');
            });
        }

        // Composite index for delivery analytics: received date with status filtering
        // Supports queries analyzing delivery completion rates and timing
        if (! $this->indexExists('pengiriman', 'idx_pengiriman_received_status')) {
            Schema::table('pengiriman', function (Blueprint $table) {
                $table->index(['received_at', 'status_id'], 'idx_pengiriman_received_status');
            });
        }

        // Index for address filtering - supports searching by destination address
        // Critical for tracking and geographic distribution analytics
        if (! $this->indexExists('pengiriman', 'idx_pengiriman_alamat_tujuan')) {
            if (config('database.default') === 'sqlite') {
                // SQLite doesn't support prefix indexes, use full column index
                DB::statement('CREATE INDEX idx_pengiriman_alamat_tujuan ON pengiriman (alamat_tujuan)');
            } else {
                // MySQL: Use prefix index for TEXT column (first 255 characters)
                DB::statement('CREATE INDEX idx_pengiriman_alamat_tujuan ON pengiriman (alamat_tujuan(255))');
            }
        }

        // MUSHAF_REQUESTS TABLE INDEXES
        // These indexes support admin filtering, geographic queries, and category-based analytics

        // Composite index for status-based filtering with chronological ordering
        // Critical for admin dashboard and request management workflows
        // Note: Basic ['status', 'created_at'] index may already exist, but we ensure the optimal order
        if (! $this->indexExists('mushaf_requests', 'idx_mushaf_status_created')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_mushaf_status_created');
            });
        }

        // Composite index for category filtering with status
        // Supports queries filtering by institution type and approval status
        if (! $this->indexExists('mushaf_requests', 'idx_mushaf_category_status')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                $table->index(['kategori_lembaga', 'status'], 'idx_mushaf_category_status');
            });
        }

        // Composite index for geographic distribution queries
        // Critical for regional analytics and distribution planning
        // Note: Basic version may exist, but we ensure it covers the most common query pattern
        if (! $this->indexExists('mushaf_requests', 'idx_mushaf_geographic')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                $table->index(['provinsi_id', 'kota_kabupaten_id'], 'idx_mushaf_geographic');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Drops all indexes created in the up() method in reverse order
     * to ensure clean rollback functionality.
     */
    public function down(): void
    {
        // Drop mushaf_requests indexes
        if ($this->indexExists('mushaf_requests', 'idx_mushaf_category_status')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                $table->dropIndex('idx_mushaf_category_status');
            });
        }

        if ($this->indexExists('mushaf_requests', 'idx_mushaf_status_created')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                $table->dropIndex('idx_mushaf_status_created');
            });
        }

        if ($this->indexExists('mushaf_requests', 'idx_mushaf_geographic')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                $table->dropIndex('idx_mushaf_geographic');
            });
        }

        // Drop pengiriman indexes
        if ($this->indexExists('pengiriman', 'idx_pengiriman_donatur_status_date')) {
            Schema::table('pengiriman', function (Blueprint $table) {
                $table->dropIndex('idx_pengiriman_donatur_status_date');
            });
        }

        if ($this->indexExists('pengiriman', 'idx_pengiriman_received_status')) {
            Schema::table('pengiriman', function (Blueprint $table) {
                $table->dropIndex('idx_pengiriman_received_status');
            });
        }

        // Drop the TEXT prefix index using raw SQL
        if ($this->indexExists('pengiriman', 'idx_pengiriman_alamat_tujuan')) {
            if (config('database.default') === 'sqlite') {
                DB::statement('DROP INDEX idx_pengiriman_alamat_tujuan');
            } else {
                DB::statement('DROP INDEX idx_pengiriman_alamat_tujuan ON pengiriman');
            }
        }
    }

    /**
     * Helper method to check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            if (config('database.default') === 'sqlite') {
                // SQLite: Check pragma_index_list
                $result = DB::select("SELECT name FROM pragma_index_list('{$table}') WHERE name = ?", [$index]);

                return ! empty($result);
            } else {
                // MySQL: Use SHOW INDEX
                $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

                return ! empty($result);
            }
        } catch (Exception $e) {
            // If we can't check, assume it doesn't exist to be safe
            return false;
        }
    }
};
