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
        // Add indexes for frequently queried columns (check if exists first)
        
        // Mushaf requests performance
        if (Schema::hasTable('mushaf_requests')) {
            Schema::table('mushaf_requests', function (Blueprint $table) {
                if (!$this->hasIndex('mushaf_requests', 'idx_mushaf_requests_status')) {
                    $table->index('status', 'idx_mushaf_requests_status');
                }
                if (!$this->hasIndex('mushaf_requests', 'idx_mushaf_requests_provinsi')) {
                    $table->index('provinsi', 'idx_mushaf_requests_provinsi');
                }
                if (!$this->hasIndex('mushaf_requests', 'idx_mushaf_requests_kota')) {
                    $table->index('kota_kabupaten', 'idx_mushaf_requests_kota');
                }
                if (!$this->hasIndex('mushaf_requests', 'idx_mushaf_requests_coordinates')) {
                    $table->index(['latitude', 'longitude'], 'idx_mushaf_requests_coordinates');
                }
            });
        }

        // Sertifikat performance  
        if (Schema::hasTable('sertifikat')) {
            Schema::table('sertifikat', function (Blueprint $table) {
                if (!$this->hasIndex('sertifikat', 'idx_sertifikat_is_sent')) {
                    $table->index('is_sent', 'idx_sertifikat_is_sent');
                }
                if (!$this->hasIndex('sertifikat', 'idx_sertifikat_generated_at')) {
                    $table->index('generated_at', 'idx_sertifikat_generated_at');
                }
            });
        }

        // WakafBatch performance
        if (Schema::hasTable('wakaf_batches')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                if (!$this->hasIndex('wakaf_batches', 'idx_wakaf_batches_status')) {
                    $table->index('status', 'idx_wakaf_batches_status');
                }
                if (!$this->hasIndex('wakaf_batches', 'idx_wakaf_batches_donatur_status')) {
                    $table->index(['donatur_id', 'status'], 'idx_wakaf_batches_donatur_status');
                }
            });
        }
    }

    /**
     * Helper method to check if index exists
     */
    private function hasIndex($table, $name)
    {
        try {
            $connection = \DB::connection();
            
            // For SQLite
            if ($connection->getDriverName() === 'sqlite') {
                $indexes = $connection->select("PRAGMA index_list({$table})");
                return collect($indexes)->contains('name', $name);
            }
            
            // For MySQL/MariaDB
            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'])) {
                $indexes = $connection->select("SHOW INDEX FROM {$table} WHERE Key_name = '{$name}'");
                return count($indexes) > 0;
            }
            
            // For PostgreSQL
            if ($connection->getDriverName() === 'pgsql') {
                $indexes = $connection->select("SELECT indexname FROM pg_indexes WHERE tablename = '{$table}' AND indexname = '{$name}'");
                return count($indexes) > 0;
            }
            
            // Default: assume index doesn't exist to avoid errors
            return false;
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes in reverse order
        
        Schema::table('wakaf_batches', function (Blueprint $table) {
            $table->dropIndex('idx_wakaf_batches_status');
            $table->dropIndex('idx_wakaf_batches_created_at');
            $table->dropIndex('idx_wakaf_batches_donatur_status');
        });

        Schema::table('sertifikat', function (Blueprint $table) {
            $table->dropIndex('idx_sertifikat_is_sent');
            $table->dropIndex('idx_sertifikat_generated_at');
            $table->dropIndex('idx_sertifikat_sent_generated');
        });

        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropIndex('idx_mushaf_requests_status');
            $table->dropIndex('idx_mushaf_requests_provinsi');
            $table->dropIndex('idx_mushaf_requests_kota');
            $table->dropIndex('idx_mushaf_requests_status_provinsi');
            $table->dropIndex('idx_mushaf_requests_coordinates');
        });

        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropIndex('idx_pengiriman_status_id');
            $table->dropIndex('idx_pengiriman_created_at');
            $table->dropIndex('idx_pengiriman_status_created');
        });

        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_whatsapp_notifications_status');
            $table->dropIndex('idx_whatsapp_notifications_sent_at');
            $table->dropIndex('idx_whatsapp_notifications_status_sent_at');
        });
    }
};
