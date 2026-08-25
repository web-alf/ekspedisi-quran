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
        Schema::table('jenis_quran', function (Blueprint $table) {
            $table->unsignedInteger('default_capacity')
                ->after('kode_jenis')
                ->default(20)
                ->comment('Kapasitas default per kerdus untuk jenis ini');
        });

        // Set default capacities berdasarkan existing hardcoded values
        DB::table('jenis_quran')->where('kode_jenis', 'A5')->update(['default_capacity' => 20]);
        DB::table('jenis_quran')->where('kode_jenis', 'A6')->update(['default_capacity' => 40]);
        DB::table('jenis_quran')->where('kode_jenis', 'IQRO')->update(['default_capacity' => 160]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jenis_quran', function (Blueprint $table) {
            $table->dropColumn('default_capacity');
        });
    }
};
