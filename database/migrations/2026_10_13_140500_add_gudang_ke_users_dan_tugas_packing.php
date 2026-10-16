<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Menautkan gudang ke:
 * - users      : gudang utama akun (dipakai SPV Gudang — wewenangnya scoping
 *                gudang ini, dan penugasan tugasnya mewarisi gudang ini);
 * - daily_packing_tasks : tugas harian untuk gudang mana — diisi saat SPV/
 *                Manager Repacking assign target ke freelancer. NULL = tidak
 *                bertagih (mis. tugas dari admin atau cron otomatis; kolom
 *                baru tidak memaksa data lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('gudang_id')->nullable()->after('is_active')->constrained('gudang', 'id')->nullOnDelete();
        });

        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->foreignId('gudang_id')->nullable()->after('user_id')->constrained('gudang', 'id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gudang_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gudang_id');
        });
    }
};
