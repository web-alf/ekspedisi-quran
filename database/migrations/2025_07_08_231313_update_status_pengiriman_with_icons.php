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
        // Update existing status records with icon values
        $statusIcons = [
            'pemesanan' => '📝',
            'produksi' => '🏭',
            'kedatangan' => '📦',
            'packing' => '🎁',
            'selesai-packing' => '✅',
            'pengiriman' => '🚚',
            'diterima' => '✅',
            'batal' => '❌',
            'pending' => '⏳',
            'dikemas' => '📦',
            'dikirim' => '🚚',
        ];
        
        foreach ($statusIcons as $slug => $icon) {
            DB::table('status_pengiriman')
                ->where('slug', $slug)
                ->update(['icon' => $icon]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear icon values
        DB::table('status_pengiriman')->update(['icon' => null]);
    }
};
