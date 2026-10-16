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
        // Add missing fields to donatur table
        Schema::table('donatur', function (Blueprint $table) {
            // Add missing basic fields
            $table->string('email_donatur')->nullable()->after('no_hp');
            $table->text('alamat_donatur')->nullable()->after('email_donatur');
            
            // Add donation-specific fields
            $table->json('jenis_wakaf_dipilih')->nullable()->after('alamat_donatur');
            $table->integer('total_a5_count')->default(0)->after('jenis_wakaf_dipilih');
            $table->integer('total_a6_count')->default(0)->after('total_a5_count');
            $table->integer('total_iqra_count')->default(0)->after('total_a6_count');
            $table->date('donation_date')->nullable()->after('total_iqra_count');
            $table->boolean('semua_atas_nama_donatur')->default(true)->after('donation_date');
            $table->text('doa_untuk_semua')->nullable()->after('semua_atas_nama_donatur');
            $table->boolean('customize_individual')->default(false)->after('doa_untuk_semua');
        });
        
        // Update pengiriman table references
        Schema::table('pengiriman', function (Blueprint $table) {
            // Only rename if column exists
            if (Schema::hasColumn('pengiriman', 'wakif_id')) {
                $table->renameColumn('wakif_id', 'donatur_id');
            }
        });
        
        // Update wakaf_batches table if it exists
        if (Schema::hasTable('wakaf_batches')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                if (Schema::hasColumn('wakaf_batches', 'wakif_count')) {
                    $table->renameColumn('wakif_count', 'donatur_count');
                }
            });
        }
        
        // Update sertifikat table if it exists
        if (Schema::hasTable('sertifikat')) {
            Schema::table('sertifikat', function (Blueprint $table) {
                if (Schema::hasColumn('sertifikat', 'wakif_id')) {
                    $table->renameColumn('wakif_id', 'donatur_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse sertifikat table
        if (Schema::hasTable('sertifikat')) {
            Schema::table('sertifikat', function (Blueprint $table) {
                if (Schema::hasColumn('sertifikat', 'donatur_id')) {
                    $table->renameColumn('donatur_id', 'wakif_id');
                }
            });
        }
        
        // Reverse wakaf_batches table
        if (Schema::hasTable('wakaf_batches')) {
            Schema::table('wakaf_batches', function (Blueprint $table) {
                if (Schema::hasColumn('wakaf_batches', 'donatur_count')) {
                    $table->renameColumn('donatur_count', 'wakif_count');
                }
            });
        }
        
        // Reverse pengiriman table
        Schema::table('pengiriman', function (Blueprint $table) {
            if (Schema::hasColumn('pengiriman', 'donatur_id')) {
                $table->renameColumn('donatur_id', 'wakif_id');
            }
        });
        
        // Remove added fields from donatur table
        Schema::table('donatur', function (Blueprint $table) {
            $table->dropColumn([
                'email_donatur',
                'alamat_donatur', 
                'jenis_wakaf_dipilih',
                'total_a5_count', 
                'total_a6_count', 
                'total_iqra_count',
                'donation_date',
                'semua_atas_nama_donatur',
                'doa_untuk_semua',
                'customize_individual'
            ]);
        });
    }
};