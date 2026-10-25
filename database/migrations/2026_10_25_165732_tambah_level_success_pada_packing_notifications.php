<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Level `success` untuk packing_notifications (25/10/2026).
 *
 * Notifikasi kerdus bersama (selesai, tersegel, siap segel) menulis level `success`, tetapi
 * enum kolomnya hanya info/warning/critical. MySQL menolaknya, sehingga "tandai selesai"
 * kerdus bersama membalas 500 setelah status kerdusnya sempat berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE packing_notifications MODIFY COLUMN level ENUM(
            'info',
            'warning',
            'critical',
            'success'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::table('packing_notifications')->where('level', 'success')->update(['level' => 'info']);

        DB::statement("ALTER TABLE packing_notifications MODIFY COLUMN level ENUM(
            'info',
            'warning',
            'critical'
        ) NOT NULL");
    }
};
