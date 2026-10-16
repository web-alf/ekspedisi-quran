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
        Schema::table('donatur', function (Blueprint $table) {
            // Rename wakif columns to donatur columns
            if (Schema::hasColumn('donatur', 'kode_wakif')) {
                $table->renameColumn('kode_wakif', 'kode_donatur');
            }
            
            if (Schema::hasColumn('donatur', 'nama_wakif')) {
                $table->renameColumn('nama_wakif', 'nama_donatur');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donatur', function (Blueprint $table) {
            // Revert column names back to wakif
            if (Schema::hasColumn('donatur', 'kode_donatur')) {
                $table->renameColumn('kode_donatur', 'kode_wakif');
            }
            
            if (Schema::hasColumn('donatur', 'nama_donatur')) {
                $table->renameColumn('nama_donatur', 'nama_wakif');
            }
        });
    }
};