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
            $table->integer('jumlah_mushaf_approved')->nullable()
                ->after('jumlah_iqra')
                ->comment('Total mushaf yang disetujui tim');

            $table->integer('jumlah_mushaf_a5_approved')->default(0)
                ->after('jumlah_mushaf_approved')
                ->comment('Jumlah A5 yang disetujui');

            $table->integer('jumlah_mushaf_a6_approved')->default(0)
                ->after('jumlah_mushaf_a5_approved')
                ->comment('Jumlah A6 yang disetujui');

            $table->integer('jumlah_iqra_approved')->default(0)
                ->after('jumlah_mushaf_a6_approved')
                ->comment('Jumlah IQRA yang disetujui');

            $table->text('catatan_perubahan_jumlah')->nullable()
                ->after('jumlah_iqra_approved')
                ->comment('Penjelasan jika jumlah disetujui berbeda dari yang diajukan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mushaf_requests', function (Blueprint $table) {
            $table->dropColumn([
                'jumlah_mushaf_approved',
                'jumlah_mushaf_a5_approved',
                'jumlah_mushaf_a6_approved',
                'jumlah_iqra_approved',
                'catatan_perubahan_jumlah',
            ]);
        });
    }
};
