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
        // Rename wakif table to donatur (skip if already exists)
        if (Schema::hasTable('wakif') && !Schema::hasTable('donatur')) {
            Schema::rename('wakif', 'donatur');
        }
        
        // Skip donatur table updates as they're handled in separate migration
        
        // Update pengiriman table references (if column exists)
        if (Schema::hasTable('pengiriman') && Schema::hasColumn('pengiriman', 'wakif_id')) {
            Schema::table('pengiriman', function (Blueprint $table) {
                $table->renameColumn('wakif_id', 'donatur_id');
            });
        }
        
        // Update wakaf_batches table (note: plural name)
        if (Schema::hasTable('wakaf_batches') && Schema::hasColumn('wakaf_batches', 'wakif_count')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                $table->renameColumn('wakif_count', 'donatur_count');
            });
        }
        
        // Update sertifikat table
        if (Schema::hasTable('sertifikat') && Schema::hasColumn('sertifikat', 'wakif_id')) {
            Schema::table('sertifikat', function (Blueprint $table) {
                $table->renameColumn('wakif_id', 'donatur_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse sertifikat table
        Schema::table('sertifikat', function (Blueprint $table) {
            $table->renameColumn('donatur_id', 'wakif_id');
        });
        
        // Reverse wakaf_batch table
        Schema::table('wakaf_batch', function (Blueprint $table) {
            $table->renameColumn('donatur_count', 'wakif_count');
        });
        
        // Reverse pengiriman table
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->renameColumn('donatur_id', 'wakif_id');
        });
        
        // Reverse donatur table structure
        Schema::table('donatur', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn([
                'jenis_wakaf_dipilih',
                'total_a5_count', 
                'total_a6_count', 
                'total_iqra_count',
                'donation_date',
                'semua_atas_nama_donatur',
                'doa_untuk_semua',
                'customize_individual'
            ]);
            
            // Rename columns back
            $table->renameColumn('kode_donatur', 'kode_wakif');
            $table->renameColumn('nama_donatur', 'nama_wakif');
            $table->renameColumn('alamat_donatur', 'alamat_wakif');
            $table->renameColumn('no_hp_donatur', 'no_hp_wakif');
            $table->renameColumn('email_donatur', 'email_wakif');
        });
        
        // Rename table back
        Schema::rename('donatur', 'wakif');
    }
};