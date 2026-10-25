<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status `ready_to_seal` dan `seal_requested` untuk alur penyelesaian kerdus bersama
 * (25/10/2026).
 *
 * SharedBoxAssignment::triggerCompletionWorkflow, DashboardController::requestBoxSealing,
 * dan komponen SealReadyBoxes sudah lama memakai kedua status ini, tetapi enum kolomnya
 * tidak pernah memuatnya — MySQL menolak penyimpanannya, daftar "siap segel" selalu kosong,
 * dan "minta segel" gagal 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE packing_boxes MODIFY COLUMN status ENUM(
            'empty',
            'filling',
            'full',
            'sealed',
            'assigned',
            'ready_to_seal',
            'seal_requested'
        ) NOT NULL DEFAULT 'empty'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Kerdus yang sedang di tengah alur kembali ke `filling` supaya enum lama bisa dipasang.
        DB::table('packing_boxes')
            ->whereIn('status', ['ready_to_seal', 'seal_requested'])
            ->update(['status' => 'filling']);

        DB::statement("ALTER TABLE packing_boxes MODIFY COLUMN status ENUM(
            'empty',
            'filling',
            'full',
            'sealed',
            'assigned'
        ) NOT NULL DEFAULT 'empty'");
    }
};
