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
        Schema::table('mushaf_requests', function (Blueprint $table) {
            // Tambah field kategori lembaga
            $table->string('kategori_lembaga')->after('nama_lembaga')->comment('Kategori lembaga/institusi');
            
            // Index untuk query berdasarkan kategori
            $table->index('kategori_lembaga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropIndex(['kategori_lembaga']);
            $table->dropColumn('kategori_lembaga');
        });
    }
};
