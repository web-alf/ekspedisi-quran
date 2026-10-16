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
        // Fix status flow - ensure proper sequential order
        
        // 1. Update status 'diterima' to proper urutan
        DB::table('status_pengiriman')
            ->where('slug', 'diterima')
            ->update([
                'urutan' => 7,
                'nama' => 'Diterima Penerima',
                'deskripsi' => 'Quran telah sampai di tangan penerima manfaat'
            ]);
        
        // 2. Deactivate old duplicate statuses that conflict with new flow
        DB::table('status_pengiriman')
            ->whereIn('slug', ['pending', 'dikemas', 'dikirim'])
            ->where('urutan', '<=', 3)
            ->update(['is_active' => 0]);
        
        // 3. Ensure proper ordering for new flow
        $statusOrdering = [
            'pemesanan' => 1, 
            'produksi' => 2,
            'kedatangan' => 3,
            'packing' => 4,
            'selesai-packing' => 5,
            'pengiriman' => 6,
            'diterima' => 7,
            'batal' => 99
        ];
        
        foreach ($statusOrdering as $slug => $urutan) {
            DB::table('status_pengiriman')
                ->where('slug', $slug)
                ->update(['urutan' => $urutan]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore old status flow if needed
        DB::table('status_pengiriman')
            ->where('slug', 'diterima')
            ->update([
                'urutan' => 4,
                'nama' => 'Diterima',
                'deskripsi' => 'Sudah diterima di tujuan'
            ]);
            
        // Reactivate old statuses
        DB::table('status_pengiriman')
            ->whereIn('slug', ['pending', 'dikemas', 'dikirim'])
            ->update(['is_active' => 1]);
    }
};
