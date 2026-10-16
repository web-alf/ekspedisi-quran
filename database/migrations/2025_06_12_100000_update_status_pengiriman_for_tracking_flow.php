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
        // Tambahkan status baru sesuai tahapan proses wakaf quran
        $statusBaru = [
            [
                'nama' => 'Proses Pemesanan',
                'slug' => 'pemesanan',
                'deskripsi' => 'Quran sedang dalam proses pemesanan',
                'warna' => 'purple',
                'urutan' => 1,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Produksi',
                'slug' => 'produksi',
                'deskripsi' => 'Quran sedang dalam proses produksi',
                'warna' => 'blue',
                'urutan' => 2,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Kedatangan/Penurunan',
                'slug' => 'kedatangan',
                'deskripsi' => 'Quran sudah datang dan dalam proses penurunan',
                'warna' => 'cyan',
                'urutan' => 3,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Packing',
                'slug' => 'packing',
                'deskripsi' => 'Quran sedang dalam proses penulisan nama, dokumentasi foto/video, dan wrapping',
                'warna' => 'teal',
                'urutan' => 4,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Selesai Packing',
                'slug' => 'selesai-packing',
                'deskripsi' => 'Quran telah selesai dikemas dan siap untuk dikirim',
                'warna' => 'green',
                'urutan' => 5,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Proses Pengiriman',
                'slug' => 'pengiriman',
                'deskripsi' => 'Quran sedang dalam proses pengiriman ke penerima manfaat',
                'warna' => 'yellow',
                'urutan' => 6,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Diterima Penerima',
                'slug' => 'diterima',
                'deskripsi' => 'Quran telah sampai di tangan penerima manfaat',
                'warna' => 'green',
                'urutan' => 7,
                'is_active' => true,
                'is_final' => true,
            ],
            [
                'nama' => 'Batal',
                'slug' => 'batal',
                'deskripsi' => 'Pengiriman dibatalkan',
                'warna' => 'red',
                'urutan' => 99,
                'is_active' => true,
                'is_final' => true,
            ],
        ];
        
        // Masukkan status baru
        foreach ($statusBaru as $status) {
            DB::table('status_pengiriman')->updateOrInsert(
                ['slug' => $status['slug']],
                $status
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback to default statuses if needed
        $defaultStatuses = [
            [
                'nama' => 'Pending',
                'slug' => 'pending',
                'deskripsi' => 'Menunggu proses pengemasan',
                'warna' => 'gray',
                'urutan' => 1,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Dikemas',
                'slug' => 'dikemas',
                'deskripsi' => 'Sedang dalam proses pengemasan',
                'warna' => 'blue',
                'urutan' => 2,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Dikirim',
                'slug' => 'dikirim',
                'deskripsi' => 'Dalam perjalanan ke tujuan',
                'warna' => 'yellow',
                'urutan' => 3,
                'is_active' => true,
                'is_final' => false,
            ],
            [
                'nama' => 'Diterima',
                'slug' => 'diterima',
                'deskripsi' => 'Sudah diterima di tujuan',
                'warna' => 'green',
                'urutan' => 4,
                'is_active' => true,
                'is_final' => true,
            ],
            [
                'nama' => 'Batal',
                'slug' => 'batal',
                'deskripsi' => 'Pengiriman dibatalkan',
                'warna' => 'red',
                'urutan' => 99,
                'is_active' => true,
                'is_final' => true,
            ],
        ];
        
        // Bersihkan status khusus
        DB::table('status_pengiriman')->whereIn('slug', ['pemesanan', 'produksi', 'kedatangan', 'packing', 'selesai-packing', 'pengiriman'])->delete();
        
        // Masukkan status default jika belum ada
        foreach ($defaultStatuses as $status) {
            DB::table('status_pengiriman')->updateOrInsert(
                ['slug' => $status['slug']],
                $status
            );
        }
    }
};
