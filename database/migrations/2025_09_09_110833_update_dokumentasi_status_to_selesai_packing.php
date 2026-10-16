<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing status from 'dokumentasi' to 'selesai-packing'
        DB::table('status_pengiriman')
            ->where('slug', 'dokumentasi')
            ->update([
                'nama' => 'Selesai Packing',
                'slug' => 'selesai-packing',
                'deskripsi' => 'Quran telah selesai dikemas dan siap untuk dikirim',
                'icon' => '✅',
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status back to 'dokumentasi'
        DB::table('status_pengiriman')
            ->where('slug', 'selesai-packing')
            ->update([
                'nama' => 'Pengiriman Dokumentasi',
                'slug' => 'dokumentasi',
                'deskripsi' => 'Dokumentasi foto/video Quran sedang dikirim ke wakif',
                'icon' => '📸',
                'updated_at' => now()
            ]);
    }
};