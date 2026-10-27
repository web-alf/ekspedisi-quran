<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis mushaf pada tugas packing harian (keputusan pemilik 28/10/2026).
 *
 * Modal Assign Target Harian kini mewajibkan satu jenis: A5/A6 (dengan atau tanpa
 * terjemahan) atau Iqro. Saat scan, resi berjenis lain DITOLAK; varian terjemahan hanya
 * keterangan karena resi belum menyimpan varian. Keduanya nullable: tugas lama dan tugas
 * dari jalur lain tetap tanpa batasan jenis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->foreignId('jenis_quran_id')->nullable()->after('gudang_id')
                ->constrained('jenis_quran')->nullOnDelete();
            // null = jenis tanpa varian (Iqro) atau tugas tanpa batasan jenis.
            $table->boolean('terjemahan')->nullable()->after('jenis_quran_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_packing_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jenis_quran_id');
            $table->dropColumn('terjemahan');
        });
    }
};
