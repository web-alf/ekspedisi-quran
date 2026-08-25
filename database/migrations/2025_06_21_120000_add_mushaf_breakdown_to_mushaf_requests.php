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
            // Tambah field untuk breakdown jumlah mushaf per jenis
            $table->integer('jumlah_mushaf_a5')->default(0)->after('jumlah_mushaf')->comment('Jumlah permintaan Mushaf Al-Qur\'an A5');
            $table->integer('jumlah_mushaf_a6')->default(0)->after('jumlah_mushaf_a5')->comment('Jumlah permintaan Mushaf Al-Qur\'an A6');
            
            // Update comment untuk field yang sudah ada
            $table->integer('jumlah_mushaf')->default(0)->comment('Total jumlah mushaf (A5 + A6)')->change();
            $table->integer('jumlah_iqra')->default(0)->comment('Jumlah permintaan IQRA\'')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropColumn(['jumlah_mushaf_a5', 'jumlah_mushaf_a6']);
            
            // Revert changes to existing columns
            $table->integer('jumlah_mushaf')->default(0)->change();
            $table->integer('jumlah_iqra')->default(0)->change();
        });
    }
};
