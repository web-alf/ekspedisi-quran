<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Selaraskan kolom `jumlah_mushaf_approved` dengan penjumlahan pecahannya
     * (A5 + A6 + IQRA).
     *
     * Sebelum perbaikan, `updateQuantities()` menulis `jumlah_mushaf_approved`
     * apa adanya dari payload frontend (yang berisi total PENGAJUAN, bukan hasil
     * edit), sementara tampilan membacanya sebagai "Total Disetujui" dan accessor
     * model menghitung dari pecahan. Akibatnya satu permintaan bisa menampilkan
     * beberapa angka berbeda.
     *
     * Hanya baris yang memang sudah menyimpang yang disentuh, sehingga baris
     * dengan `jumlah_mushaf_approved` NULL tetap NULL — nilai NULL itu sendiri
     * adalah penanda "jumlah disetujui belum pernah ditetapkan".
     */
    public function up(): void
    {
        DB::table('mushaf_requests')
            ->whereNotNull('jumlah_mushaf_approved')
            ->whereRaw('jumlah_mushaf_approved <> (jumlah_mushaf_a5_approved + jumlah_mushaf_a6_approved + jumlah_iqra_approved)')
            ->update([
                'jumlah_mushaf_approved' => DB::raw(
                    'jumlah_mushaf_a5_approved + jumlah_mushaf_a6_approved + jumlah_iqra_approved'
                ),
            ]);
    }

    /**
     * Tidak ada yang perlu dibalik: nilai lama sudah menyimpang dan tidak
     * tersimpan di mana pun.
     */
    public function down(): void
    {
        //
    }
};
