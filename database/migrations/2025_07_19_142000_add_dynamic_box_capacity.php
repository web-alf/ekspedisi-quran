<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add quantity field to packing_items table
        Schema::table('packing_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('urutan_dalam_box');
        });

        // Check if we're using SQLite for testing
        if (config('database.default') === 'sqlite') {
            // SQLite-compatible queries using subqueries instead of JOINs in UPDATE

            // Update existing packing_items to have quantity based on pengiriman
            DB::statement('
                UPDATE packing_items 
                SET quantity = (
                    SELECT p.jumlah_quran 
                    FROM pengiriman p 
                    WHERE p.id = packing_items.pengiriman_id
                )
                WHERE quantity = 1 AND pengiriman_id IS NOT NULL
            ');

            // Update existing boxes capacity based on jenis_quran (A5=20, A6=40, IQRO=160)
            DB::statement("
                UPDATE packing_boxes 
                SET kapasitas = (
                    SELECT CASE 
                        WHEN jq.kode_jenis = 'A5' THEN 20
                        WHEN jq.kode_jenis = 'A6' THEN 40  
                        WHEN jq.kode_jenis = 'IQRO' THEN 160
                        ELSE 20
                    END
                    FROM jenis_quran jq 
                    WHERE jq.id = packing_boxes.jenis_quran_id
                )
                WHERE jenis_quran_id IS NOT NULL
            ");
        } else {
            // MySQL-compatible queries using JOIN syntax

            // Update existing packing_items to have quantity based on pengiriman
            DB::statement('
                UPDATE packing_items pi 
                JOIN pengiriman p ON pi.pengiriman_id = p.id 
                SET pi.quantity = p.jumlah_quran 
                WHERE pi.quantity = 1
            ');

            // Update existing boxes capacity based on jenis_quran
            DB::statement("
                UPDATE packing_boxes pb 
                JOIN jenis_quran jq ON pb.jenis_quran_id = jq.id 
                SET pb.kapasitas = CASE 
                    WHEN jq.kode_jenis = 'A5' THEN 20
                    WHEN jq.kode_jenis = 'A6' THEN 40  
                    WHEN jq.kode_jenis = 'IQRO' THEN 160
                    ELSE 20
                END
                WHERE pb.jenis_quran_id IS NOT NULL
            ");
        }

        // Update jumlah_terisi to reflect actual quantity (works on both SQLite and MySQL)
        DB::statement('
            UPDATE packing_boxes 
            SET jumlah_terisi = (
                SELECT COALESCE(SUM(pi.quantity), 0) 
                FROM packing_items pi 
                WHERE pi.packing_box_id = packing_boxes.id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_items', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });

        // Reset all box capacities to 20 (original default)
        DB::statement('UPDATE packing_boxes SET kapasitas = 20');
    }
};
